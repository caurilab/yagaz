<?php

namespace Tests\Feature;

use App\Enums\NiveauAcces;
use App\Enums\RoleMembership;
use App\Models\Commande;
use App\Models\FormatBouteille;
use App\Models\Membership;
use App\Models\Organisation;
use App\Models\Site;
use App\Models\SiteAcces;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Boucle foyer → dépôt → livraison → livreur (contrat API doc 10) : cycle de
 * vie complet, cloisonnement, transitions illégales, stock insuffisant,
 * affectation d'un livreur hors dépôt, proposition acceptée/refusée,
 * commission enregistrée.
 */
class CommandeLivraisonApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Crée un foyer avec un site et un accès propriétaire.
     *
     * @return array{0: User, 1: Site}
     */
    private function foyerAvecSite(): array
    {
        $user = User::factory()->create();
        $site = Site::factory()->create(['cree_par' => $user->id]);
        SiteAcces::forceCreate([
            'site_id' => $site->id,
            'user_id' => $user->id,
            'niveau' => NiveauAcces::Proprietaire->value,
        ]);

        return [$user, $site];
    }

    private function gerantDe(Organisation $depot): User
    {
        $user = User::factory()->create();
        Membership::forceCreate([
            'user_id' => $user->id,
            'organisation_id' => $depot->id,
            'role' => RoleMembership::GerantDepot->value,
            'actif' => true,
        ]);

        return $user;
    }

    private function livreurDe(Organisation $depot): User
    {
        $user = User::factory()->create();
        Membership::forceCreate([
            'user_id' => $user->id,
            'organisation_id' => $depot->id,
            'role' => RoleMembership::Livreur->value,
            'actif' => true,
        ]);

        return $user;
    }

    private function depotAvecStock(int $pleines = 10, int $vides = 2): array
    {
        $depot = Organisation::factory()->depot()->create();
        $format = FormatBouteille::factory()->create();
        $stock = Stock::create([
            'organisation_id' => $depot->id,
            'format_id' => $format->id,
            'pleines' => $pleines,
            'vides' => $vides,
            'seuil_plein_bas' => 3,
        ]);

        return [$depot, $format, $stock];
    }

    // === Boucle complète =================================================

    public function test_boucle_complete_foyer_depot_livreur(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        [$depot, $format] = $this->depotAvecStock(pleines: 10, vides: 2);
        $gerant = $this->gerantDe($depot);
        $livreur = $this->livreurDe($depot);

        // Le foyer commande.
        Sanctum::actingAs($foyer);
        $creation = $this->postJson('/api/commandes', [
            'site_uuid' => $site->uuid,
            'format_id' => $format->id,
            'quantite' => 2,
            'depot_uuid' => $depot->uuid,
        ]);
        $creation->assertCreated();
        $creation->assertJsonPath('data.statut', 'confirmee');
        $creation->assertJsonPath('data.origine', 'foyer');
        $creation->assertJsonPath('data.commission_g', 100);
        $uuid = $creation->json('data.uuid');

        // Le foyer suit sa commande.
        $detail = $this->getJson("/api/commandes/{$uuid}");
        $detail->assertOk();
        $detail->assertJsonPath('data.statut', 'confirmee');

        // Le dépôt prépare : décrémente les pleines, mouvement `vente`.
        Sanctum::actingAs($gerant);
        $preparer = $this->patchJson("/api/commandes/{$uuid}/preparer");
        $preparer->assertOk();
        $preparer->assertJsonPath('data.statut', 'preparee');
        $this->assertDatabaseHas('stocks', [
            'organisation_id' => $depot->id,
            'format_id' => $format->id,
            'pleines' => 8,
        ]);
        $this->assertDatabaseHas('mouvements_stock', [
            'type' => 'vente',
            'delta_pleines' => -2,
        ]);

        // Le dépôt affecte le livreur.
        $affectation = $this->postJson("/api/commandes/{$uuid}/livraison", [
            'livreur_user_id' => $livreur->uuid,
        ]);
        $affectation->assertCreated();
        $affectation->assertJsonPath('data.statut', 'affectee');
        $livraisonId = $affectation->json('data.id');
        $this->assertDatabaseHas('livraisons', [
            'id' => $livraisonId,
            'livreur_user_id' => $livreur->id,
            'statut' => 'affectee',
        ]);

        // Le livreur voit sa mission (et seulement la sienne).
        Sanctum::actingAs($livreur);
        $missions = $this->getJson('/api/livreur/missions');
        $missions->assertOk();
        $missions->assertJsonCount(1, 'data');
        $missions->assertJsonPath('data.0.commande_uuid', $uuid);

        // en_route ⇒ commande en_livraison.
        $enRoute = $this->patchJson("/api/livraisons/{$livraisonId}/statut", ['statut' => 'en_route']);
        $enRoute->assertOk();
        $enRoute->assertJsonPath('data.statut', 'en_route');
        $this->assertDatabaseHas('commandes', ['uuid' => $uuid, 'statut' => 'en_livraison']);

        // livree ⇒ commande livree.
        $livree = $this->patchJson("/api/livraisons/{$livraisonId}/statut", ['statut' => 'livree']);
        $livree->assertOk();
        $this->assertDatabaseHas('commandes', ['uuid' => $uuid, 'statut' => 'livree']);

        // vide_recupere ⇒ vides + quantite, mouvement `retour_vide`.
        $videRecupere = $this->patchJson("/api/livraisons/{$livraisonId}/statut", ['statut' => 'vide_recupere']);
        $videRecupere->assertOk();
        $videRecupere->assertJsonPath('data.vides_recuperes', 2);
        $this->assertDatabaseHas('stocks', [
            'organisation_id' => $depot->id,
            'format_id' => $format->id,
            'vides' => 4,
        ]);
        $this->assertDatabaseHas('mouvements_stock', [
            'type' => 'retour_vide',
            'delta_vides' => 2,
            'livraison_id' => $livraisonId,
        ]);
    }

    // === Cloisonnement ====================================================

    public function test_un_depot_ne_voit_pas_les_commandes_d_un_autre_depot(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        [$depotA, $formatA] = $this->depotAvecStock();
        [$depotB] = $this->depotAvecStock();
        $gerantB = $this->gerantDe($depotB);

        Sanctum::actingAs($foyer);
        $creation = $this->postJson('/api/commandes', [
            'site_uuid' => $site->uuid,
            'format_id' => $formatA->id,
            'quantite' => 1,
            'depot_uuid' => $depotA->uuid,
        ]);
        $uuid = $creation->json('data.uuid');

        Sanctum::actingAs($gerantB);
        $this->getJson("/api/depots/{$depotB->uuid}/commandes")->assertOk()->assertJsonCount(0, 'data');
        $this->patchJson("/api/commandes/{$uuid}/preparer")->assertNotFound();
        $this->getJson("/api/depots/{$depotA->uuid}/stocks")->assertNotFound();
    }

    public function test_un_livreur_ne_voit_pas_les_missions_d_un_autre_livreur(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        [$depot, $format] = $this->depotAvecStock();
        $gerant = $this->gerantDe($depot);
        $livreurA = $this->livreurDe($depot);
        $livreurB = $this->livreurDe($depot);

        Sanctum::actingAs($foyer);
        $uuid = $this->postJson('/api/commandes', [
            'site_uuid' => $site->uuid,
            'format_id' => $format->id,
            'quantite' => 1,
            'depot_uuid' => $depot->uuid,
        ])->json('data.uuid');

        Sanctum::actingAs($gerant);
        $this->patchJson("/api/commandes/{$uuid}/preparer")->assertOk();
        $livraisonId = $this->postJson("/api/commandes/{$uuid}/livraison", [
            'livreur_user_id' => $livreurA->uuid,
        ])->json('data.id');

        Sanctum::actingAs($livreurB);
        $this->getJson('/api/livreur/missions')->assertOk()->assertJsonCount(0, 'data');
        $this->patchJson("/api/livraisons/{$livraisonId}/statut", ['statut' => 'en_route'])->assertNotFound();

        Sanctum::actingAs($livreurA);
        $this->getJson('/api/livreur/missions')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_un_foyer_ne_voit_pas_la_commande_d_un_autre_foyer(): void
    {
        [$foyerA, $siteA] = $this->foyerAvecSite();
        [$foyerB] = $this->foyerAvecSite();
        [$depot, $format] = $this->depotAvecStock();

        Sanctum::actingAs($foyerA);
        $uuid = $this->postJson('/api/commandes', [
            'site_uuid' => $siteA->uuid,
            'format_id' => $format->id,
            'quantite' => 1,
            'depot_uuid' => $depot->uuid,
        ])->json('data.uuid');

        Sanctum::actingAs($foyerB);
        $this->getJson("/api/commandes/{$uuid}")->assertNotFound();
        $this->getJson('/api/commandes')->assertOk()->assertJsonCount(0, 'data');
    }

    // === Transitions illégales ===========================================

    public function test_preparer_une_commande_deja_livree_est_refuse(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        [$depot, $format] = $this->depotAvecStock();
        $gerant = $this->gerantDe($depot);
        $livreur = $this->livreurDe($depot);

        Sanctum::actingAs($foyer);
        $uuid = $this->postJson('/api/commandes', [
            'site_uuid' => $site->uuid,
            'format_id' => $format->id,
            'quantite' => 1,
            'depot_uuid' => $depot->uuid,
        ])->json('data.uuid');

        Sanctum::actingAs($gerant);
        $this->patchJson("/api/commandes/{$uuid}/preparer")->assertOk();
        $livraisonId = $this->postJson("/api/commandes/{$uuid}/livraison", [
            'livreur_user_id' => $livreur->uuid,
        ])->json('data.id');

        Sanctum::actingAs($livreur);
        $this->patchJson("/api/livraisons/{$livraisonId}/statut", ['statut' => 'en_route'])->assertOk();
        $this->patchJson("/api/livraisons/{$livraisonId}/statut", ['statut' => 'livree'])->assertOk();

        // La commande est `livree` : la préparer à nouveau est illégal.
        Sanctum::actingAs($gerant);
        $this->patchJson("/api/commandes/{$uuid}/preparer")->assertStatus(422);
    }

    public function test_le_statut_de_livraison_ne_peut_pas_reculer_ni_sauter(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        [$depot, $format] = $this->depotAvecStock();
        $gerant = $this->gerantDe($depot);
        $livreur = $this->livreurDe($depot);

        Sanctum::actingAs($foyer);
        $uuid = $this->postJson('/api/commandes', [
            'site_uuid' => $site->uuid,
            'format_id' => $format->id,
            'quantite' => 1,
            'depot_uuid' => $depot->uuid,
        ])->json('data.uuid');

        Sanctum::actingAs($gerant);
        $this->patchJson("/api/commandes/{$uuid}/preparer")->assertOk();
        $livraisonId = $this->postJson("/api/commandes/{$uuid}/livraison", [
            'livreur_user_id' => $livreur->uuid,
        ])->json('data.id');

        Sanctum::actingAs($livreur);
        // Sauter directement à `livree` sans passer par `en_route`.
        $this->patchJson("/api/livraisons/{$livraisonId}/statut", ['statut' => 'livree'])->assertStatus(422);

        $this->patchJson("/api/livraisons/{$livraisonId}/statut", ['statut' => 'en_route'])->assertOk();
        // Répéter `en_route` (recul/statu quo) est refusé.
        $this->patchJson("/api/livraisons/{$livraisonId}/statut", ['statut' => 'en_route'])->assertStatus(422);
    }

    // === Stock insuffisant ================================================

    public function test_preparer_avec_stock_insuffisant_est_refuse_et_ne_decrement_pas(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        [$depot, $format] = $this->depotAvecStock(pleines: 1);
        $gerant = $this->gerantDe($depot);

        Sanctum::actingAs($foyer);
        $uuid = $this->postJson('/api/commandes', [
            'site_uuid' => $site->uuid,
            'format_id' => $format->id,
            'quantite' => 5,
            'depot_uuid' => $depot->uuid,
        ])->json('data.uuid');

        Sanctum::actingAs($gerant);
        $this->patchJson("/api/commandes/{$uuid}/preparer")->assertStatus(422);

        $this->assertDatabaseHas('stocks', [
            'organisation_id' => $depot->id,
            'format_id' => $format->id,
            'pleines' => 1,
        ]);
        $this->assertDatabaseHas('commandes', ['uuid' => $uuid, 'statut' => 'confirmee']);
    }

    // === Affectation d'un livreur non membre =============================

    public function test_affecter_un_livreur_non_membre_du_depot_est_refuse(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        [$depot, $format] = $this->depotAvecStock();
        $gerant = $this->gerantDe($depot);
        $inconnu = User::factory()->create();

        Sanctum::actingAs($foyer);
        $uuid = $this->postJson('/api/commandes', [
            'site_uuid' => $site->uuid,
            'format_id' => $format->id,
            'quantite' => 1,
            'depot_uuid' => $depot->uuid,
        ])->json('data.uuid');

        Sanctum::actingAs($gerant);
        $this->patchJson("/api/commandes/{$uuid}/preparer")->assertOk();
        $this->postJson("/api/commandes/{$uuid}/livraison", [
            'livreur_user_id' => $inconnu->uuid,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('livraisons', ['commande_id' => Commande::where('uuid', $uuid)->value('id')]);
    }

    // === Proposition dépôt → foyer ========================================

    public function test_le_foyer_accepte_une_proposition(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        [$depot, $format] = $this->depotAvecStock();
        $gerant = $this->gerantDe($depot);

        Sanctum::actingAs($gerant);
        $proposition = $this->postJson("/api/depots/{$depot->uuid}/propositions", [
            'site_uuid' => $site->uuid,
            'format_id' => $format->id,
            'quantite' => 1,
        ]);
        $proposition->assertCreated();
        $proposition->assertJsonPath('data.statut', 'proposee');
        $uuid = $proposition->json('data.uuid');

        Sanctum::actingAs($foyer);
        $reponse = $this->postJson("/api/commandes/{$uuid}/reponse", ['accepte' => true]);
        $reponse->assertOk();
        $reponse->assertJsonPath('data.statut', 'confirmee');
    }

    public function test_le_foyer_refuse_une_proposition(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        [$depot, $format] = $this->depotAvecStock();
        $gerant = $this->gerantDe($depot);

        Sanctum::actingAs($gerant);
        $uuid = $this->postJson("/api/depots/{$depot->uuid}/propositions", [
            'site_uuid' => $site->uuid,
            'format_id' => $format->id,
            'quantite' => 1,
        ])->json('data.uuid');

        Sanctum::actingAs($foyer);
        $reponse = $this->postJson("/api/commandes/{$uuid}/reponse", ['accepte' => false]);
        $reponse->assertOk();
        $reponse->assertJsonPath('data.statut', 'annulee');
    }

    public function test_un_autre_foyer_ne_peut_pas_repondre_a_une_proposition(): void
    {
        [, $site] = $this->foyerAvecSite();
        [$autreFoyer] = $this->foyerAvecSite();
        [$depot, $format] = $this->depotAvecStock();
        $gerant = $this->gerantDe($depot);

        Sanctum::actingAs($gerant);
        $uuid = $this->postJson("/api/depots/{$depot->uuid}/propositions", [
            'site_uuid' => $site->uuid,
            'format_id' => $format->id,
            'quantite' => 1,
        ])->json('data.uuid');

        Sanctum::actingAs($autreFoyer);
        $this->postJson("/api/commandes/{$uuid}/reponse", ['accepte' => true])->assertNotFound();
    }

    // === Commission =======================================================

    public function test_la_commission_est_enregistree_a_la_creation(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        [$depot, $format] = $this->depotAvecStock();

        Sanctum::actingAs($foyer);
        $reponse = $this->postJson('/api/commandes', [
            'site_uuid' => $site->uuid,
            'format_id' => $format->id,
            'quantite' => 3,
            'depot_uuid' => $depot->uuid,
        ]);

        $reponse->assertCreated();
        $reponse->assertJsonPath('data.commission_g', 150);
        $this->assertDatabaseHas('commandes', [
            'uuid' => $reponse->json('data.uuid'),
            'commission_g' => 150,
        ]);
    }
}

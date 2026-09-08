<?php

namespace Tests\Feature;

use App\Enums\NiveauAcces;
use App\Enums\RoleMembership;
use App\Enums\TypeAlerte;
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
 * Réappro dépôt → mandataire (ADR 0009, maillon D) : production automatique
 * à la baisse du stock, idempotence, confirmation par le dépôt, visibilité
 * côté mandataire, notifications (4e événement, contrat API doc 11 §3).
 */
class ReapproApiTest extends TestCase
{
    use RefreshDatabase;

    private function gerantDepotDe(Organisation $depot): User
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

    private function mandataireDe(Organisation $mandataire): User
    {
        $user = User::factory()->create();
        Membership::forceCreate([
            'user_id' => $user->id,
            'organisation_id' => $mandataire->id,
            'role' => RoleMembership::Mandataire->value,
            'actif' => true,
        ]);

        return $user;
    }

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

    /**
     * Dépôt rattaché à un mandataire, avec un stock proche du seuil bas
     * (`seuil_plein_bas = 3`, `pleines = 4`) : une commande foyer de 2
     * bouteilles fait passer `pleines` à 2, sous le seuil.
     *
     * @return array{0: Organisation, 1: Organisation, 2: FormatBouteille, 3: Stock}
     */
    private function depotEnTensionAvecMandataire(int $pleines = 4, int $seuil = 3): array
    {
        $mandataire = Organisation::factory()->mandataire()->create();
        $depot = Organisation::factory()->depot()->create(['parent_id' => $mandataire->id]);
        $format = FormatBouteille::factory()->create();
        $stock = Stock::forceCreate([
            'organisation_id' => $depot->id,
            'format_id' => $format->id,
            'pleines' => $pleines,
            'vides' => 0,
            'seuil_plein_bas' => $seuil,
        ]);

        return [$depot, $mandataire, $format, $stock];
    }

    private function etablirClienteleDepot(Site $site, Organisation $depot, FormatBouteille $format): void
    {
        Commande::factory()->create([
            'site_id' => $site->id,
            'cible_org_id' => $depot->id,
            'format_id' => $format->id,
        ]);
    }

    // === Production automatique ==========================================

    public function test_preparer_une_commande_foyer_sous_le_seuil_produit_un_reappro_propose(): void
    {
        [$depot, $mandataire, $format] = $this->depotEnTensionAvecMandataire(pleines: 4, seuil: 3);
        [$foyer, $site] = $this->foyerAvecSite();
        $this->etablirClienteleDepot($site, $depot, $format);
        $gerantDepot = $this->gerantDepotDe($depot);

        Sanctum::actingAs($foyer);
        $uuid = $this->postJson('/api/commandes', [
            'site_uuid' => $site->uuid,
            'format_id' => $format->id,
            'quantite' => 2,
            'depot_uuid' => $depot->uuid,
        ])->json('data.uuid');

        // pleines passe de 4 à 2, sous le seuil (3) : un réappro doit apparaître.
        Sanctum::actingAs($gerantDepot);
        $this->patchJson("/api/commandes/{$uuid}/preparer")->assertOk();

        $this->assertDatabaseHas('commandes', [
            'origine' => 'depot',
            'demandeur_org_id' => $depot->id,
            'cible_org_id' => $mandataire->id,
            'format_id' => $format->id,
            'statut' => 'proposee',
        ]);

        // quantite = seuil_plein_bas*2 - pleines = 3*2 - 2 = 4.
        $reappro = Commande::where('demandeur_org_id', $depot->id)->where('origine', 'depot')->first();
        $this->assertSame(4, $reappro->quantite);

        // Notification au gérant du dépôt (4e événement, préparation).
        $this->assertDatabaseHas('alertes', [
            'destinataire_user_id' => $gerantDepot->id,
            'type' => TypeAlerte::ReapproPrepare->value,
            'commande_id' => $reappro->id,
        ]);
    }

    public function test_un_second_decrement_ne_cree_pas_de_doublon_mais_met_a_jour_la_quantite(): void
    {
        [$depot, $mandataire, $format] = $this->depotEnTensionAvecMandataire(pleines: 10, seuil: 3);
        [$foyer, $site] = $this->foyerAvecSite();
        $this->etablirClienteleDepot($site, $depot, $format);
        $gerantDepot = $this->gerantDepotDe($depot);

        Sanctum::actingAs($foyer);
        $premiere = $this->postJson('/api/commandes', [
            'site_uuid' => $site->uuid,
            'format_id' => $format->id,
            'quantite' => 8,
            'depot_uuid' => $depot->uuid,
        ])->json('data.uuid');

        Sanctum::actingAs($gerantDepot);
        // pleines : 10 -> 2, sous le seuil (3) : réappro produit, quantite = 3*2-2 = 4.
        $this->patchJson("/api/commandes/{$premiere}/preparer")->assertOk();
        $this->assertSame(1, Commande::where('origine', 'depot')->count());

        Sanctum::actingAs($foyer);
        $seconde = $this->postJson('/api/commandes', [
            'site_uuid' => $site->uuid,
            'format_id' => $format->id,
            'quantite' => 1,
            'depot_uuid' => $depot->uuid,
        ])->json('data.uuid');

        Sanctum::actingAs($gerantDepot);
        // pleines : 2 -> 1, toujours sous le seuil : pas de doublon, quantite mise à jour = 3*2-1 = 5.
        $this->patchJson("/api/commandes/{$seconde}/preparer")->assertOk();

        // Toujours un seul réappro (idempotence), quantité mise à jour.
        $this->assertSame(1, Commande::where('origine', 'depot')->count());
        $this->assertDatabaseHas('commandes', [
            'origine' => 'depot',
            'demandeur_org_id' => $depot->id,
            'statut' => 'proposee',
            'quantite' => 5,
        ]);
    }

    public function test_un_ajustement_manuel_de_stock_sous_le_seuil_produit_aussi_un_reappro(): void
    {
        [$depot, $mandataire, $format, $stock] = $this->depotEnTensionAvecMandataire(pleines: 10, seuil: 3);
        $gerantDepot = $this->gerantDepotDe($depot);

        Sanctum::actingAs($gerantDepot);
        $this->patchJson("/api/depots/{$depot->uuid}/stocks/{$format->id}", ['pleines' => 1])->assertOk();

        $this->assertDatabaseHas('commandes', [
            'origine' => 'depot',
            'demandeur_org_id' => $depot->id,
            'cible_org_id' => $mandataire->id,
            'statut' => 'proposee',
        ]);
    }

    public function test_un_depot_sans_mandataire_parent_ne_produit_aucun_reappro(): void
    {
        $depot = Organisation::factory()->depot()->create(['parent_id' => null]);
        $format = FormatBouteille::factory()->create();
        Stock::forceCreate([
            'organisation_id' => $depot->id,
            'format_id' => $format->id,
            'pleines' => 10,
            'vides' => 0,
            'seuil_plein_bas' => 3,
        ]);
        $gerantDepot = $this->gerantDepotDe($depot);

        Sanctum::actingAs($gerantDepot);
        $this->patchJson("/api/depots/{$depot->uuid}/stocks/{$format->id}", ['pleines' => 1])
            ->assertOk();

        $this->assertDatabaseCount('commandes', 0);
    }

    // === Confirmation dépôt ===============================================

    public function test_le_gerant_du_depot_confirme_et_ajuste_un_reappro(): void
    {
        [$depot, $mandataire, $format] = $this->depotEnTensionAvecMandataire();
        $gerantDepot = $this->gerantDepotDe($depot);
        $mandataireUser = $this->mandataireDe($mandataire);

        $reappro = Commande::factory()->depuisDepot()->create([
            'demandeur_org_id' => $depot->id,
            'cible_org_id' => $mandataire->id,
            'format_id' => $format->id,
            'quantite' => 5,
        ]);

        Sanctum::actingAs($gerantDepot);

        // Le réappro apparaît dans la liste du dépôt.
        $liste = $this->getJson("/api/depots/{$depot->uuid}/reappros");
        $liste->assertOk();
        $liste->assertJsonCount(1, 'data');
        $liste->assertJsonPath('data.0.uuid', $reappro->uuid);
        $liste->assertJsonPath('data.0.statut', 'proposee');

        $confirmation = $this->postJson("/api/commandes/{$reappro->uuid}/confirmer-reappro", ['quantite' => 6]);
        $confirmation->assertOk();
        $confirmation->assertJsonPath('data.statut', 'confirmee');
        $confirmation->assertJsonPath('data.quantite', 6);

        $this->assertDatabaseHas('commandes', [
            'uuid' => $reappro->uuid,
            'statut' => 'confirmee',
            'quantite' => 6,
        ]);

        // Notification au mandataire (4e événement, confirmation).
        $this->assertDatabaseHas('alertes', [
            'destinataire_user_id' => $mandataireUser->id,
            'type' => TypeAlerte::ReapproConfirme->value,
            'commande_id' => $reappro->id,
        ]);
    }

    public function test_un_autre_depot_ne_peut_pas_confirmer_le_reappro(): void
    {
        [$depot, $mandataire, $format] = $this->depotEnTensionAvecMandataire();
        $autreDepot = Organisation::factory()->depot()->create();
        $gerantAutreDepot = $this->gerantDepotDe($autreDepot);

        $reappro = Commande::factory()->depuisDepot()->create([
            'demandeur_org_id' => $depot->id,
            'cible_org_id' => $mandataire->id,
            'format_id' => $format->id,
        ]);

        Sanctum::actingAs($gerantAutreDepot);
        $this->postJson("/api/commandes/{$reappro->uuid}/confirmer-reappro")->assertNotFound();
        $this->getJson("/api/depots/{$autreDepot->uuid}/reappros")->assertOk()->assertJsonCount(0, 'data');

        $this->assertDatabaseHas('commandes', ['uuid' => $reappro->uuid, 'statut' => 'proposee']);
    }

    public function test_confirmer_un_reappro_deja_confirme_est_refuse(): void
    {
        [$depot, $mandataire, $format] = $this->depotEnTensionAvecMandataire();
        $gerantDepot = $this->gerantDepotDe($depot);

        $reappro = Commande::factory()->depuisDepot()->create([
            'demandeur_org_id' => $depot->id,
            'cible_org_id' => $mandataire->id,
            'format_id' => $format->id,
        ]);

        Sanctum::actingAs($gerantDepot);
        $this->postJson("/api/commandes/{$reappro->uuid}/confirmer-reappro")->assertOk();
        $this->postJson("/api/commandes/{$reappro->uuid}/confirmer-reappro")->assertStatus(422);
    }

    // === Visibilité mandataire =============================================

    public function test_le_mandataire_voit_le_reappro_confirme_et_le_depot_demandeur(): void
    {
        [$depot, $mandataire, $format] = $this->depotEnTensionAvecMandataire();
        $gerantDepot = $this->gerantDepotDe($depot);
        $mandataireUser = $this->mandataireDe($mandataire);

        $reappro = Commande::factory()->depuisDepot()->create([
            'demandeur_org_id' => $depot->id,
            'cible_org_id' => $mandataire->id,
            'format_id' => $format->id,
        ]);

        Sanctum::actingAs($gerantDepot);
        $this->postJson("/api/commandes/{$reappro->uuid}/confirmer-reappro")->assertOk();

        Sanctum::actingAs($mandataireUser);
        $reponse = $this->getJson("/api/mandataires/{$mandataire->uuid}/reappros");
        $reponse->assertOk();
        $reponse->assertJsonCount(1, 'data');
        $reponse->assertJsonPath('data.0.uuid', $reappro->uuid);
        $reponse->assertJsonPath('data.0.statut', 'confirmee');
        $reponse->assertJsonPath('data.0.depot.uuid', $depot->uuid);
    }

    public function test_un_mandataire_ne_voit_pas_les_reappros_d_un_autre_mandataire(): void
    {
        [$depotA, $mandataireA, $formatA] = $this->depotEnTensionAvecMandataire();
        [$depotB, $mandataireB, $formatB] = $this->depotEnTensionAvecMandataire();
        $mandataireUserA = $this->mandataireDe($mandataireA);

        Commande::factory()->depuisDepot()->create([
            'demandeur_org_id' => $depotB->id,
            'cible_org_id' => $mandataireB->id,
            'format_id' => $formatB->id,
            'statut' => 'confirmee',
        ]);

        Sanctum::actingAs($mandataireUserA);
        $this->getJson("/api/mandataires/{$mandataireA->uuid}/reappros")->assertOk()->assertJsonCount(0, 'data');
    }
}

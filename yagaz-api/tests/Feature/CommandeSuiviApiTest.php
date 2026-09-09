<?php

namespace Tests\Feature;

use App\Enums\NiveauAcces;
use App\Enums\RoleMembership;
use App\Enums\StatutCommande;
use App\Enums\StatutLivraison;
use App\Models\Commande;
use App\Models\Livraison;
use App\Models\Membership;
use App\Models\Organisation;
use App\Models\Site;
use App\Models\SiteAcces;
use App\Models\User;
use App\Services\Geo\Distance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Suivi/ETA d'une commande (`GET /api/commandes/{uuid}/suivi`) : timeline
 * d'étapes selon le statut courant, ETA estimée depuis la distance
 * dépôt↔site quand `en_livraison`, cloisonnement.
 */
class CommandeSuiviApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Site}
     */
    private function foyerAvecSite(array $siteAttributs = []): array
    {
        $user = User::factory()->create();
        $site = Site::factory()->create(array_merge(['cree_par' => $user->id], $siteAttributs));
        SiteAcces::forceCreate([
            'site_id' => $site->id,
            'user_id' => $user->id,
            'niveau' => NiveauAcces::Proprietaire->value,
        ]);

        return [$user, $site];
    }

    public function test_le_suivi_d_une_commande_confirmee_ne_renvoie_que_les_deux_premieres_etapes(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        $depot = Organisation::factory()->depot()->create([
            'nom' => 'Dépôt Gaz Cocody',
            'telephone' => '+225 07 10 11 12 13',
        ]);
        $commande = Commande::factory()->create([
            'demandeur_user_id' => $foyer->id,
            'site_id' => $site->id,
            'cible_org_id' => $depot->id,
            'statut' => StatutCommande::Confirmee,
        ]);

        Sanctum::actingAs($foyer);
        $reponse = $this->getJson("/api/commandes/{$commande->uuid}/suivi");

        $reponse->assertOk();
        $reponse->assertJsonPath('data.statut_courant', 'confirmee');
        $reponse->assertJsonPath('data.depot.nom', 'Dépôt Gaz Cocody');
        $reponse->assertJsonPath('data.depot.telephone', '+225 07 10 11 12 13');
        $reponse->assertJsonPath('data.etapes.0.cle', 'passee');
        $reponse->assertJsonPath('data.etapes.0.atteinte', true);
        $reponse->assertJsonPath('data.etapes.1.cle', 'confirmee');
        $reponse->assertJsonPath('data.etapes.1.atteinte', true);
        $reponse->assertJsonPath('data.etapes.1.courante', true);
        $this->assertNotNull($reponse->json('data.etapes.1.date'));
        $reponse->assertJsonPath('data.etapes.2.cle', 'preparee');
        $reponse->assertJsonPath('data.etapes.2.atteinte', false);
        $this->assertNull($reponse->json('data.etapes.2.date'));
        $reponse->assertJsonPath('data.etapes.3.atteinte', false);
        $reponse->assertJsonPath('data.etapes.4.atteinte', false);

        // Pas encore en livraison : aucune ETA, quelles que soient les
        // coordonnées (dépôt/site en ont via la factory).
        $this->assertNull($reponse->json('data.eta_minutes'));
    }

    public function test_le_suivi_d_une_commande_preparee_atteint_les_trois_premieres_etapes(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        $depot = Organisation::factory()->depot()->create();
        $commande = Commande::factory()->create([
            'demandeur_user_id' => $foyer->id,
            'site_id' => $site->id,
            'cible_org_id' => $depot->id,
            'statut' => StatutCommande::Preparee,
        ]);

        Sanctum::actingAs($foyer);
        $reponse = $this->getJson("/api/commandes/{$commande->uuid}/suivi");

        $reponse->assertOk();
        $reponse->assertJsonPath('data.etapes.2.cle', 'preparee');
        $reponse->assertJsonPath('data.etapes.2.atteinte', true);
        $reponse->assertJsonPath('data.etapes.2.courante', true);
        $reponse->assertJsonPath('data.etapes.3.atteinte', false);
    }

    public function test_le_suivi_en_livraison_avec_coordonnees_calcule_l_eta(): void
    {
        [$foyer, $site] = $this->foyerAvecSite(['lat' => 5.400000, 'lng' => -4.000000]);
        $depot = Organisation::factory()->depot()->create(['lat' => 5.336400, 'lng' => -4.026700]);
        $commande = Commande::factory()->create([
            'demandeur_user_id' => $foyer->id,
            'site_id' => $site->id,
            'cible_org_id' => $depot->id,
            'statut' => StatutCommande::EnLivraison,
        ]);
        $livreur = User::factory()->create(['name' => 'Kouassi Yao']);
        Livraison::forceCreate([
            'commande_id' => $commande->id,
            'livreur_user_id' => $livreur->id,
            'statut' => StatutLivraison::EnRoute,
            'pleines_deposees' => $commande->quantite,
            'vides_recuperes' => 0,
            'affectee_at' => now()->subMinutes(20),
            'en_route_at' => now()->subMinutes(10),
        ]);

        Sanctum::actingAs($foyer);
        $reponse = $this->getJson("/api/commandes/{$commande->uuid}/suivi");

        $reponse->assertOk();
        $reponse->assertJsonPath('data.statut_courant', 'en_livraison');
        $reponse->assertJsonPath('data.etapes.3.cle', 'en_livraison');
        $reponse->assertJsonPath('data.etapes.3.atteinte', true);
        $reponse->assertJsonPath('data.etapes.3.courante', true);
        $reponse->assertJsonPath('data.livraison.statut', 'en_route');
        $reponse->assertJsonPath('data.livraison.livreur', 'Kouassi Yao');

        $distanceKm = $reponse->json('data.distance_km');
        $this->assertNotNull($distanceKm);

        $distanceAttendue = app(Distance::class)->kilometres(5.3364, -4.0267, 5.4, -4.0);
        $this->assertEqualsWithDelta($distanceAttendue, $distanceKm, 0.01);

        $vitesse = (float) config('livraison.vitesse_urbaine_kmh');
        $etaAttendu = (int) round($distanceAttendue / $vitesse * 60);
        $reponse->assertJsonPath('data.eta_minutes', $etaAttendu);
    }

    public function test_le_suivi_en_livraison_sans_coordonnees_ne_calcule_pas_l_eta(): void
    {
        [$foyer, $site] = $this->foyerAvecSite(['lat' => null, 'lng' => null]);
        $depot = Organisation::factory()->depot()->create(['lat' => null, 'lng' => null]);
        $commande = Commande::factory()->create([
            'demandeur_user_id' => $foyer->id,
            'site_id' => $site->id,
            'cible_org_id' => $depot->id,
            'statut' => StatutCommande::EnLivraison,
        ]);
        Livraison::forceCreate([
            'commande_id' => $commande->id,
            'livreur_user_id' => null,
            'statut' => StatutLivraison::EnRoute,
            'pleines_deposees' => $commande->quantite,
            'vides_recuperes' => 0,
            'affectee_at' => now()->subMinutes(20),
            'en_route_at' => now()->subMinutes(10),
        ]);

        Sanctum::actingAs($foyer);
        $reponse = $this->getJson("/api/commandes/{$commande->uuid}/suivi");

        $reponse->assertOk();
        $this->assertNull($reponse->json('data.distance_km'));
        $this->assertNull($reponse->json('data.eta_minutes'));
    }

    public function test_le_suivi_d_une_commande_livree_atteint_toutes_les_etapes(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        $depot = Organisation::factory()->depot()->create();
        $commande = Commande::factory()->create([
            'demandeur_user_id' => $foyer->id,
            'site_id' => $site->id,
            'cible_org_id' => $depot->id,
            'statut' => StatutCommande::Livree,
        ]);
        Livraison::forceCreate([
            'commande_id' => $commande->id,
            'livreur_user_id' => null,
            'statut' => StatutLivraison::Livree,
            'pleines_deposees' => $commande->quantite,
            'vides_recuperes' => 0,
            'affectee_at' => now()->subHour(),
            'en_route_at' => now()->subMinutes(40),
            'livree_at' => now()->subMinutes(5),
        ]);

        Sanctum::actingAs($foyer);
        $reponse = $this->getJson("/api/commandes/{$commande->uuid}/suivi");

        $reponse->assertOk();
        $reponse->assertJsonPath('data.etapes.4.cle', 'livree');
        $reponse->assertJsonPath('data.etapes.4.atteinte', true);
        $reponse->assertJsonPath('data.etapes.4.courante', true);
        $this->assertNotNull($reponse->json('data.etapes.4.date'));
        // Livrée : plus d'ETA pertinente (livraison terminée).
        $this->assertNull($reponse->json('data.eta_minutes'));
    }

    public function test_l_organisation_cible_peut_voir_le_suivi(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        $depot = Organisation::factory()->depot()->create();
        $commande = Commande::factory()->create([
            'demandeur_user_id' => $foyer->id,
            'site_id' => $site->id,
            'cible_org_id' => $depot->id,
            'statut' => StatutCommande::Confirmee,
        ]);

        $gerant = User::factory()->create();
        Membership::forceCreate([
            'user_id' => $gerant->id,
            'organisation_id' => $depot->id,
            'role' => RoleMembership::GerantDepot->value,
            'actif' => true,
        ]);

        Sanctum::actingAs($gerant);
        $this->getJson("/api/commandes/{$commande->uuid}/suivi")->assertOk();
    }

    public function test_un_autre_foyer_ne_peut_pas_voir_le_suivi(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        [$autreFoyer] = $this->foyerAvecSite();
        $depot = Organisation::factory()->depot()->create();
        $commande = Commande::factory()->create([
            'demandeur_user_id' => $foyer->id,
            'site_id' => $site->id,
            'cible_org_id' => $depot->id,
            'statut' => StatutCommande::Confirmee,
        ]);

        Sanctum::actingAs($autreFoyer);
        $this->getJson("/api/commandes/{$commande->uuid}/suivi")->assertNotFound();
    }
}

<?php

namespace Tests\Feature;

use App\Enums\CanalAlerte;
use App\Enums\ModePaiement;
use App\Enums\NiveauAcces;
use App\Enums\StatutAlerte;
use App\Enums\StatutCommande;
use App\Enums\StatutLivraison;
use App\Enums\StatutPaiement;
use App\Enums\TypeAlerte;
use App\Models\Alerte;
use App\Models\Bouteille;
use App\Models\Commande;
use App\Models\FormatBouteille;
use App\Models\Livraison;
use App\Models\Organisation;
use App\Models\Paiement;
use App\Models\SessionCuisson;
use App\Models\Site;
use App\Models\SiteAcces;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * `GET /api/historique` (doc 13, §1) : timeline unifiée du foyer
 * (commandes, paiements, alertes, sessions de cuisson), triée récent→ancien,
 * strictement cloisonnée au périmètre foyer.
 */
class HistoriqueApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Site, 2: Bouteille}
     */
    private function foyerAvecBouteille(): array
    {
        $user = User::factory()->create();
        $site = Site::factory()->create(['cree_par' => $user->id]);

        SiteAcces::forceCreate([
            'site_id' => $site->id,
            'user_id' => $user->id,
            'niveau' => NiveauAcces::Proprietaire->value,
        ]);

        $bouteille = Bouteille::factory()->create([
            'site_id' => $site->id,
            'format_id' => FormatBouteille::factory()->create(),
        ]);

        return [$user, $site, $bouteille];
    }

    /**
     * Fabrique un jeu complet d'événements (commande livrée + paiement réglé
     * + alerte seuil bas + session de cuisson terminée) pour un site donné.
     */
    private function fabriquerEvenements(Site $site, Bouteille $bouteille): void
    {
        $depot = Organisation::factory()->depot()->create();

        $commande = Commande::factory()->create([
            'site_id' => $site->id,
            'cible_org_id' => $depot->id,
            'format_id' => $bouteille->format_id,
            'quantite' => 2,
            'statut' => StatutCommande::Livree,
            'mode_paiement' => ModePaiement::MobileMoney,
        ]);

        Livraison::forceCreate([
            'commande_id' => $commande->id,
            'statut' => StatutLivraison::Livree,
            'pleines_deposees' => 2,
            'vides_recuperes' => 0,
            'affectee_at' => now()->subHours(3),
            'en_route_at' => now()->subHours(2),
            'livree_at' => now()->subHour(),
        ]);

        Paiement::factory()->create([
            'commande_id' => $commande->id,
            'statut' => StatutPaiement::Regle,
            'montant' => 13000,
        ]);

        Alerte::create([
            'bouteille_id' => $bouteille->id,
            'type' => TypeAlerte::SeuilBas,
            'statut' => StatutAlerte::Emise,
            'canal' => CanalAlerte::Push,
        ]);

        SessionCuisson::create([
            'site_id' => $site->id,
            'debut_at' => now()->subMinutes(90),
            'fin_at' => now()->subMinutes(60),
            'temp_max_c' => 55.0,
        ]);
    }

    public function test_renvoie_les_evenements_du_foyer_tries_recents_dabord(): void
    {
        [$user, $site, $bouteille] = $this->foyerAvecBouteille();
        $this->fabriquerEvenements($site, $bouteille);

        Sanctum::actingAs($user);
        $reponse = $this->getJson('/api/historique?site_uuid='.$site->uuid);

        $reponse->assertOk();

        $types = collect($reponse->json('data'))->pluck('type');

        $this->assertTrue($types->contains('commande_creee'));
        $this->assertTrue($types->contains('commande_confirmee'));
        $this->assertTrue($types->contains('commande_livree'));
        $this->assertTrue($types->contains('paiement_initie'));
        $this->assertTrue($types->contains('paiement_regle'));
        $this->assertTrue($types->contains('alerte_seuil_bas'));
        $this->assertTrue($types->contains('cuisson_debut'));
        $this->assertTrue($types->contains('cuisson_fin'));

        $icones = collect($reponse->json('data'))->pluck('icone')->unique()->sort()->values();
        $this->assertSame(['alerte', 'commande', 'cuisson', 'paiement'], $icones->all());

        $dates = collect($reponse->json('data'))->pluck('date')->map(fn ($d) => strtotime($d));
        $triees = $dates->sort()->reverse()->values();
        $this->assertSame($triees->all(), $dates->all());

        $this->assertArrayHasKey('pagination', $reponse->json());
        $this->assertGreaterThanOrEqual(8, $reponse->json('pagination.total'));
    }

    public function test_un_autre_foyer_ne_voit_pas_ces_evenements(): void
    {
        [, $site, $bouteille] = $this->foyerAvecBouteille();
        $this->fabriquerEvenements($site, $bouteille);

        $autreUser = User::factory()->create();
        $autreSite = Site::factory()->create(['cree_par' => $autreUser->id]);
        SiteAcces::forceCreate([
            'site_id' => $autreSite->id,
            'user_id' => $autreUser->id,
            'niveau' => NiveauAcces::Proprietaire->value,
        ]);

        Sanctum::actingAs($autreUser);
        $reponse = $this->getJson('/api/historique');

        $reponse->assertOk();
        $this->assertSame(0, $reponse->json('pagination.total'));
        $this->assertSame([], $reponse->json('data'));
    }

    public function test_site_uuid_hors_perimetre_renvoie_404(): void
    {
        [, $site, $bouteille] = $this->foyerAvecBouteille();
        $this->fabriquerEvenements($site, $bouteille);

        $autreUser = User::factory()->create();

        Sanctum::actingAs($autreUser);
        $this->getJson('/api/historique?site_uuid='.$site->uuid)->assertNotFound();
    }

    public function test_le_filtre_type_restreint_par_categorie(): void
    {
        [$user, $site, $bouteille] = $this->foyerAvecBouteille();
        $this->fabriquerEvenements($site, $bouteille);

        Sanctum::actingAs($user);
        $reponse = $this->getJson('/api/historique?site_uuid='.$site->uuid.'&type=cuisson');

        $reponse->assertOk();

        $icones = collect($reponse->json('data'))->pluck('icone')->unique();
        $this->assertSame(['cuisson'], $icones->all());
    }
}

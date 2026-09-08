<?php

namespace Tests\Feature;

use App\Enums\RoleBouteille;
use App\Enums\RoleMembership;
use App\Models\Bouteille;
use App\Models\Commande;
use App\Models\FormatBouteille;
use App\Models\LivreurHabituel;
use App\Models\Membership;
use App\Models\NiveauCourant;
use App\Models\Organisation;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * ADR 0009, maillon B — `GET /api/depots/{orgUuid}/foyers-en-tension` : la
 * file des foyers de la zone de desserte du dépôt (déjà client OU rattaché
 * par un livreur habituel membre du dépôt) ayant une bouteille active sous
 * son seuil bas. Réponse minimale et actionnable.
 */
class DepotFoyersEnTensionApiTest extends TestCase
{
    use RefreshDatabase;

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

    /**
     * Un site avec une bouteille active et son niveau courant. `niveauPct`
     * sous `seuilBasPct` = site en tension. `$format` est partagé entre
     * plusieurs appels d'un même test (évite d'épuiser le pool `unique()` de
     * la factory de format).
     */
    private function siteAvecNiveau(int $niveauPct, FormatBouteille $format, int $seuilBasPct = 15, string $zone = 'Plateau'): Site
    {
        $site = Site::factory()->create(['zone' => $zone]);
        $bouteille = Bouteille::factory()->create([
            'site_id' => $site->id,
            'format_id' => $format->id,
            'role_bouteille' => RoleBouteille::Active,
            'seuil_bas_pct' => $seuilBasPct,
        ]);
        NiveauCourant::create([
            'bouteille_id' => $bouteille->id,
            'gaz_g' => 0,
            'niveau_pct' => $niveauPct,
            'autonomie_min' => 0,
            'debit_g_par_h' => 100,
            'calcule_at' => now(),
        ]);

        return $site;
    }

    private function rendreClient(Site $site, Organisation $depot, FormatBouteille $format): void
    {
        Commande::factory()->create([
            'site_id' => $site->id,
            'cible_org_id' => $depot->id,
            'format_id' => $format->id,
        ]);
    }

    public function test_la_file_ne_renvoie_que_les_foyers_de_la_zone_de_desserte_en_tension(): void
    {
        $depot = Organisation::factory()->depot()->create();
        $gerant = $this->gerantDe($depot);
        $format = FormatBouteille::factory()->create(['code' => 'B12', 'marque' => 'Total']);

        // Client du dépôt, en tension : inclus.
        $siteClientEnTension = $this->siteAvecNiveau(niveauPct: 10, format: $format, zone: 'Almadies');
        $this->rendreClient($siteClientEnTension, $depot, $format);

        // Client du dépôt, PAS en tension : exclu.
        $siteClientHorsTension = $this->siteAvecNiveau(niveauPct: 80, format: $format, zone: 'Ngor');
        $this->rendreClient($siteClientHorsTension, $depot, $format);

        // Pas client, rattaché par un livreur habituel MEMBRE du dépôt, en
        // tension : inclus.
        $livreur = $this->livreurDe($depot);
        $siteRattacheEnTension = $this->siteAvecNiveau(niveauPct: 5, format: $format, zone: 'Yoff');
        LivreurHabituel::create([
            'site_id' => $siteRattacheEnTension->id,
            'livreur_user_id' => $livreur->id,
            'actif' => true,
        ]);

        // Pas client, pas rattaché : exclu même s'il est en tension.
        $this->siteAvecNiveau(niveauPct: 2, format: $format, zone: 'Ouakam');

        Sanctum::actingAs($gerant);
        $reponse = $this->getJson("/api/depots/{$depot->uuid}/foyers-en-tension");

        $reponse->assertOk();
        $reponse->assertJsonCount(2, 'data');

        $uuids = collect($reponse->json('data'))->pluck('site_uuid');
        $this->assertTrue($uuids->contains($siteClientEnTension->uuid));
        $this->assertTrue($uuids->contains($siteRattacheEnTension->uuid));
        $this->assertFalse($uuids->contains($siteClientHorsTension->uuid));

        // Réponse minimale : pas de niveau exact exposé.
        $texteBrut = (string) $reponse->getContent();
        $this->assertStringNotContainsString('niveau_pct', $texteBrut);
        $this->assertStringNotContainsString('autonomie', $texteBrut);

        $entree = collect($reponse->json('data'))->firstWhere('site_uuid', $siteClientEnTension->uuid);
        $this->assertSame('Almadies', $entree['zone']);
        $this->assertSame('B12', $entree['format']['code']);
    }

    public function test_un_depot_ne_voit_pas_les_foyers_en_tension_d_un_autre_depot(): void
    {
        $depotA = Organisation::factory()->depot()->create();
        $depotB = Organisation::factory()->depot()->create();
        $gerantB = $this->gerantDe($depotB);

        $format = FormatBouteille::factory()->create();
        $siteA = $this->siteAvecNiveau(niveauPct: 5, format: $format);
        $this->rendreClient($siteA, $depotA, $format);

        Sanctum::actingAs($gerantB);
        $this->getJson("/api/depots/{$depotB->uuid}/foyers-en-tension")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_un_gerant_d_un_autre_depot_recoit_404_hors_perimetre(): void
    {
        $depot = Organisation::factory()->depot()->create();
        $intrus = User::factory()->create();

        Sanctum::actingAs($intrus);
        $this->getJson("/api/depots/{$depot->uuid}/foyers-en-tension")->assertNotFound();
    }

    /**
     * Lacune signalée (audit) : un livreur (non-gérant) membre du dépôt
     * n'est pas pour autant `gerant_depot` — `OrganisationPolicy::gererDepot`
     * réserve la file au gérant direct. `404`, comme un intrus complet.
     */
    public function test_un_livreur_non_gerant_recoit_404_sur_foyers_en_tension(): void
    {
        $depot = Organisation::factory()->depot()->create();
        $livreur = $this->livreurDe($depot);

        Sanctum::actingAs($livreur);
        $this->getJson("/api/depots/{$depot->uuid}/foyers-en-tension")->assertNotFound();
    }

    /**
     * Correctif [FAIBLE] bucketiser la distance (ADR 0008, borne à la zone) :
     * `distance_km` n'expose jamais la distance brute — arrondie au 0,5 km
     * supérieur.
     */
    public function test_la_distance_est_bucketisee_au_demi_kilometre_superieur(): void
    {
        // ~0,022° de latitude ≈ 2,4 km à l'équateur : distance brute non ronde.
        $depot = Organisation::factory()->depot()->create(['lat' => 14.7000, 'lng' => -17.4500]);
        $gerant = $this->gerantDe($depot);
        $format = FormatBouteille::factory()->create();

        $site = $this->siteAvecNiveau(niveauPct: 5, format: $format, zone: 'Almadies');
        $site->forceFill(['lat' => 14.7220, 'lng' => -17.4500])->save();
        $this->rendreClient($site, $depot, $format);

        Sanctum::actingAs($gerant);
        $reponse = $this->getJson("/api/depots/{$depot->uuid}/foyers-en-tension");
        $reponse->assertOk();

        $distance = $reponse->json('data.0.distance_km');
        $this->assertNotNull($distance);
        // Multiple exact de 0,5, jamais la valeur brute (haversine ≈ 2,44 km).
        $this->assertSame(0.0, fmod($distance * 10, 5));
        $this->assertGreaterThanOrEqual(2.44, $distance);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\RoleMembership;
use App\Models\Commande;
use App\Models\FormatBouteille;
use App\Models\Membership;
use App\Models\Organisation;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Distributeur — demande régionale agrégée (contrat API doc 11, §2) :
 * agrégats par zone/format, jamais de donnée individuelle de foyer, et
 * cloisonné à sa propre branche (mandataires enfants → leurs dépôts).
 */
class DistributeurApiTest extends TestCase
{
    use RefreshDatabase;

    private function distributeurDe(Organisation $distributeur): User
    {
        $user = User::factory()->create();
        Membership::forceCreate([
            'user_id' => $user->id,
            'organisation_id' => $distributeur->id,
            'role' => RoleMembership::Distributeur->value,
            'actif' => true,
        ]);

        return $user;
    }

    /**
     * Distributeur → mandataire → dépôt, avec un format et un stock.
     *
     * @return array{0: Organisation, 1: Organisation, 2: Organisation, 3: FormatBouteille}
     */
    private function brancheComplete(string $zone = 'Dakar'): array
    {
        $distributeur = Organisation::factory()->distributeur()->create();
        $mandataire = Organisation::factory()->mandataire()->create(['parent_id' => $distributeur->id]);
        $depot = Organisation::factory()->depot()->create(['parent_id' => $mandataire->id, 'zone' => $zone]);
        $format = FormatBouteille::factory()->create();

        return [$distributeur, $mandataire, $depot, $format];
    }

    public function test_la_demande_est_agregee_par_zone_et_par_format_sans_donnee_de_foyer(): void
    {
        [$distributeur, , $depot, $format] = $this->brancheComplete('Dakar');
        $gerant = $this->distributeurDe($distributeur);

        // Deux commandes foyer livrées au même dépôt/format : agrégées ensemble.
        Commande::factory()->create([
            'cible_org_id' => $depot->id,
            'format_id' => $format->id,
            'quantite' => 2,
            'statut' => 'livree',
        ]);
        Commande::factory()->create([
            'cible_org_id' => $depot->id,
            'format_id' => $format->id,
            'quantite' => 3,
            'statut' => 'livree',
        ]);

        Sanctum::actingAs($gerant);
        $reponse = $this->getJson('/api/distributeurs/'.$distributeur->uuid.'/demande?depuis='.now()->subDay()->toDateString().'&jusqua='.now()->addDay()->toDateString());

        $reponse->assertOk();
        $reponse->assertJsonCount(1, 'data');
        $reponse->assertJsonPath('data.0.zone', 'Dakar');
        $reponse->assertJsonPath('data.0.format_code', $format->code);
        $reponse->assertJsonPath('data.0.volume', 5);

        $texteJson = $reponse->getContent();
        $this->assertStringNotContainsString('site_id', $texteJson);
        $this->assertStringNotContainsString('user_id', $texteJson);
        $this->assertStringNotContainsString('bouteille_id', $texteJson);
        $this->assertStringNotContainsString('demandeur_user_id', $texteJson);
    }

    public function test_les_reappros_sont_inclus_dans_la_demande_agregee(): void
    {
        [$distributeur, $mandataire, $depot, $format] = $this->brancheComplete('Thiès');
        $gerant = $this->distributeurDe($distributeur);

        Commande::factory()->depuisDepot()->create([
            'demandeur_org_id' => $depot->id,
            'cible_org_id' => $mandataire->id,
            'format_id' => $format->id,
            'quantite' => 7,
        ]);

        Sanctum::actingAs($gerant);
        $reponse = $this->getJson('/api/distributeurs/'.$distributeur->uuid.'/demande?depuis='.now()->subDay()->toDateString().'&jusqua='.now()->addDay()->toDateString());

        $reponse->assertOk();
        $reponse->assertJsonCount(1, 'data');
        $reponse->assertJsonPath('data.0.zone', 'Thiès');
        $reponse->assertJsonPath('data.0.volume', 7);
    }

    public function test_les_zones_en_tension_sont_remontees(): void
    {
        [$distributeur, , $depot, $format] = $this->brancheComplete('Dakar');
        $gerant = $this->distributeurDe($distributeur);

        Stock::forceCreate([
            'organisation_id' => $depot->id,
            'format_id' => $format->id,
            'pleines' => 0,
            'vides' => 10,
            'seuil_plein_bas' => 5,
        ]);

        Sanctum::actingAs($gerant);
        $reponse = $this->getJson('/api/distributeurs/'.$distributeur->uuid.'/zones');

        $reponse->assertOk();
        $reponse->assertJsonCount(1, 'data');
        $reponse->assertJsonPath('data.0.zone', 'Dakar');
        $reponse->assertJsonPath('data.0.depots_en_rupture', 1);
        $reponse->assertJsonPath('data.0.depots_total', 1);
        $reponse->assertJsonPath('data.0.vides_accumules', 10);
        $reponse->assertJsonPath('data.0.niveau', 'critique');
    }

    public function test_les_volumes_sont_agreges_par_zone_et_periode(): void
    {
        [$distributeur, , $depot, $format] = $this->brancheComplete('Dakar');
        $gerant = $this->distributeurDe($distributeur);

        Commande::factory()->create([
            'cible_org_id' => $depot->id,
            'format_id' => $format->id,
            'quantite' => 4,
            'statut' => 'livree',
        ]);

        Sanctum::actingAs($gerant);
        $reponse = $this->getJson('/api/distributeurs/'.$distributeur->uuid.'/volumes?depuis='.now()->subDay()->toDateString().'&jusqua='.now()->addDay()->toDateString().'&pas=mois');

        $reponse->assertOk();
        $reponse->assertJsonCount(1, 'data');
        $reponse->assertJsonPath('data.0.zone', 'Dakar');
        $reponse->assertJsonPath('data.0.volume', 4);
    }

    public function test_un_distributeur_ne_voit_que_sa_propre_branche(): void
    {
        [$distributeurA, , $depotA, $formatA] = $this->brancheComplete('Dakar');
        [, , $depotB, $formatB] = $this->brancheComplete('Kaolack');
        $gerantA = $this->distributeurDe($distributeurA);

        Commande::factory()->create(['cible_org_id' => $depotA->id, 'format_id' => $formatA->id, 'quantite' => 2, 'statut' => 'livree']);
        Commande::factory()->create(['cible_org_id' => $depotB->id, 'format_id' => $formatB->id, 'quantite' => 9, 'statut' => 'livree']);

        Sanctum::actingAs($gerantA);
        $reponse = $this->getJson('/api/distributeurs/'.$distributeurA->uuid.'/demande?depuis='.now()->subDay()->toDateString().'&jusqua='.now()->addDay()->toDateString());

        $reponse->assertOk();
        $reponse->assertJsonCount(1, 'data');
        $reponse->assertJsonPath('data.0.zone', 'Dakar');
        $reponse->assertJsonPath('data.0.volume', 2);
    }

    public function test_un_autre_distributeur_ne_peut_pas_acceder_a_une_branche_hors_perimetre(): void
    {
        [$distributeurA] = $this->brancheComplete('Dakar');
        [$distributeurB] = $this->brancheComplete('Kaolack');
        $gerantB = $this->distributeurDe($distributeurB);

        Sanctum::actingAs($gerantB);
        $this->getJson('/api/distributeurs/'.$distributeurA->uuid.'/zones')->assertNotFound();
    }

    public function test_un_mandataire_ne_peut_pas_acceder_aux_agregats_distributeur(): void
    {
        [$distributeur, $mandataire] = $this->brancheComplete('Dakar');
        $gerantMandataire = User::factory()->create();
        Membership::forceCreate([
            'user_id' => $gerantMandataire->id,
            'organisation_id' => $mandataire->id,
            'role' => RoleMembership::Mandataire->value,
            'actif' => true,
        ]);

        Sanctum::actingAs($gerantMandataire);
        $this->getJson('/api/distributeurs/'.$distributeur->uuid.'/zones')->assertNotFound();
    }

    public function test_demande_exige_depuis_et_jusqua(): void
    {
        [$distributeur] = $this->brancheComplete();
        $gerant = $this->distributeurDe($distributeur);

        Sanctum::actingAs($gerant);
        $this->getJson('/api/distributeurs/'.$distributeur->uuid.'/demande')->assertUnprocessable();
    }

    public function test_une_periode_de_plus_de_24_mois_est_rejetee(): void
    {
        [$distributeur] = $this->brancheComplete();
        $gerant = $this->distributeurDe($distributeur);

        Sanctum::actingAs($gerant);
        $reponse = $this->getJson('/api/distributeurs/'.$distributeur->uuid.'/demande?depuis=1900-01-01&jusqua=2100-01-01');

        $reponse->assertUnprocessable();
        $reponse->assertJsonValidationErrors('jusqua');
    }

    public function test_une_periode_de_24_mois_ou_moins_est_acceptee(): void
    {
        [$distributeur] = $this->brancheComplete();
        $gerant = $this->distributeurDe($distributeur);

        Sanctum::actingAs($gerant);
        $reponse = $this->getJson('/api/distributeurs/'.$distributeur->uuid.'/demande?depuis='.now()->subMonths(24)->toDateString().'&jusqua='.now()->toDateString());

        $reponse->assertOk();
    }
}

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
 * Mandataire — vue consolidée des dépôts, réappros, tournées (contrat API
 * doc 11, §1) : cloisonnement à ses propres dépôts, réappros filtrés,
 * création/validation d'une tournée avec lignes (pleines + vides), ajustement.
 */
class MandataireApiTest extends TestCase
{
    use RefreshDatabase;

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

    private function livreurDe(Organisation $organisation): User
    {
        $user = User::factory()->create();
        Membership::forceCreate([
            'user_id' => $user->id,
            'organisation_id' => $organisation->id,
            'role' => RoleMembership::Livreur->value,
            'actif' => true,
        ]);

        return $user;
    }

    /**
     * @return array{0: Organisation, 1: Organisation, 2: Organisation}
     */
    private function mandataireAvecDeuxDepots(): array
    {
        $mandataire = Organisation::factory()->mandataire()->create();
        $depot1 = Organisation::factory()->depot()->create(['parent_id' => $mandataire->id, 'zone' => 'Dakar']);
        $depot2 = Organisation::factory()->depot()->create(['parent_id' => $mandataire->id, 'zone' => 'Thiès']);

        return [$mandataire, $depot1, $depot2];
    }

    // === Vue consolidée des dépôts =======================================

    public function test_le_mandataire_voit_la_consolidation_de_ses_depots(): void
    {
        [$mandataire, $depot1, $depot2] = $this->mandataireAvecDeuxDepots();
        $format = FormatBouteille::factory()->create();
        $gerant = $this->mandataireDe($mandataire);

        // Dépôt 1 : en tension (pleines sous le seuil).
        Stock::forceCreate([
            'organisation_id' => $depot1->id,
            'format_id' => $format->id,
            'pleines' => 1,
            'vides' => 2,
            'seuil_plein_bas' => 5,
        ]);

        // Dépôt 2 : sain.
        Stock::forceCreate([
            'organisation_id' => $depot2->id,
            'format_id' => $format->id,
            'pleines' => 20,
            'vides' => 1,
            'seuil_plein_bas' => 5,
        ]);

        Sanctum::actingAs($gerant);
        $reponse = $this->getJson("/api/mandataires/{$mandataire->uuid}/depots");

        $reponse->assertOk();
        $reponse->assertJsonCount(2, 'data');

        $depotsJson = collect($reponse->json('data'))->keyBy('uuid');
        $this->assertTrue($depotsJson[$depot1->uuid]['en_tension']);
        $this->assertFalse($depotsJson[$depot2->uuid]['en_tension']);
        $this->assertSame('Dakar', $depotsJson[$depot1->uuid]['zone']);
    }

    public function test_un_mandataire_ne_voit_pas_les_depots_d_un_autre_mandataire(): void
    {
        [$mandataireA] = $this->mandataireAvecDeuxDepots();
        [$mandataireB] = $this->mandataireAvecDeuxDepots();
        $gerantB = $this->mandataireDe($mandataireB);

        Sanctum::actingAs($gerantB);

        $this->getJson("/api/mandataires/{$mandataireA->uuid}/depots")->assertNotFound();
    }

    public function test_un_gerant_de_depot_ne_peut_pas_acceder_a_la_vue_mandataire(): void
    {
        [$mandataire, $depot1] = $this->mandataireAvecDeuxDepots();
        $gerantDepot = User::factory()->create();
        Membership::forceCreate([
            'user_id' => $gerantDepot->id,
            'organisation_id' => $depot1->id,
            'role' => RoleMembership::GerantDepot->value,
            'actif' => true,
        ]);

        Sanctum::actingAs($gerantDepot);

        $this->getJson("/api/mandataires/{$mandataire->uuid}/depots")->assertNotFound();
    }

    // === Réappros =========================================================

    public function test_les_reappros_sont_filtres_par_mandataire(): void
    {
        [$mandataireA, $depotA] = $this->mandataireAvecDeuxDepots();
        [$mandataireB, $depotB] = $this->mandataireAvecDeuxDepots();
        $gerantA = $this->mandataireDe($mandataireA);

        $reapproA = Commande::factory()->depuisDepot()->create([
            'demandeur_org_id' => $depotA->id,
            'cible_org_id' => $mandataireA->id,
        ]);
        Commande::factory()->depuisDepot()->create([
            'demandeur_org_id' => $depotB->id,
            'cible_org_id' => $mandataireB->id,
        ]);

        Sanctum::actingAs($gerantA);
        $reponse = $this->getJson("/api/mandataires/{$mandataireA->uuid}/reappros");

        $reponse->assertOk();
        $reponse->assertJsonCount(1, 'data');
        $reponse->assertJsonPath('data.0.uuid', $reapproA->uuid);
    }

    // === Tournées ==========================================================

    public function test_creation_et_validation_d_une_tournee_avec_lignes(): void
    {
        [$mandataire, $depot1, $depot2] = $this->mandataireAvecDeuxDepots();
        $format = FormatBouteille::factory()->create();
        $gerant = $this->mandataireDe($mandataire);
        $livreur = $this->livreurDe($mandataire);

        Sanctum::actingAs($gerant);
        $reponse = $this->postJson("/api/mandataires/{$mandataire->uuid}/tournees", [
            'date' => now()->toDateString(),
            'livreur_user_id' => $livreur->uuid,
            'lignes' => [
                ['depot_uuid' => $depot1->uuid, 'format_id' => $format->id, 'pleines' => 10, 'vides_a_recuperer' => 4],
                ['depot_uuid' => $depot2->uuid, 'format_id' => $format->id, 'pleines' => 6, 'vides_a_recuperer' => 2],
            ],
        ]);

        $reponse->assertCreated();
        $reponse->assertJsonPath('data.statut', 'validee');
        $reponse->assertJsonCount(2, 'data.lignes');
        $reponse->assertJsonPath('data.lignes.0.pleines', 10);
        $reponse->assertJsonPath('data.lignes.0.vides_a_recuperer', 4);
        $reponse->assertJsonPath('data.livreur.uuid', $livreur->uuid);

        $this->assertDatabaseCount('tournee_lignes', 2);
        $this->assertDatabaseHas('tournees', ['organisation_id' => $mandataire->id, 'statut' => 'validee']);
    }

    public function test_creation_de_tournee_vers_un_depot_hors_perimetre_est_refusee(): void
    {
        [$mandataireA] = $this->mandataireAvecDeuxDepots();
        [, $depotB] = $this->mandataireAvecDeuxDepots();
        $format = FormatBouteille::factory()->create();
        $gerantA = $this->mandataireDe($mandataireA);

        Sanctum::actingAs($gerantA);
        $reponse = $this->postJson("/api/mandataires/{$mandataireA->uuid}/tournees", [
            'date' => now()->toDateString(),
            'lignes' => [
                ['depot_uuid' => $depotB->uuid, 'format_id' => $format->id, 'pleines' => 1, 'vides_a_recuperer' => 0],
            ],
        ]);

        $reponse->assertStatus(422);
        $this->assertDatabaseCount('tournee_lignes', 0);
    }

    public function test_creation_de_tournee_avec_un_livreur_hors_mandataire_est_refusee(): void
    {
        [$mandataire, $depot1] = $this->mandataireAvecDeuxDepots();
        $format = FormatBouteille::factory()->create();
        $gerant = $this->mandataireDe($mandataire);
        $inconnu = User::factory()->create();

        Sanctum::actingAs($gerant);
        $reponse = $this->postJson("/api/mandataires/{$mandataire->uuid}/tournees", [
            'date' => now()->toDateString(),
            'livreur_user_id' => $inconnu->uuid,
            'lignes' => [
                ['depot_uuid' => $depot1->uuid, 'format_id' => $format->id, 'pleines' => 1, 'vides_a_recuperer' => 0],
            ],
        ]);

        $reponse->assertStatus(422);
    }

    public function test_patch_tournee_ajuste_le_statut_et_remplace_les_lignes(): void
    {
        [$mandataire, $depot1, $depot2] = $this->mandataireAvecDeuxDepots();
        $format = FormatBouteille::factory()->create();
        $gerant = $this->mandataireDe($mandataire);

        Sanctum::actingAs($gerant);
        $creation = $this->postJson("/api/mandataires/{$mandataire->uuid}/tournees", [
            'date' => now()->toDateString(),
            'lignes' => [
                ['depot_uuid' => $depot1->uuid, 'format_id' => $format->id, 'pleines' => 10, 'vides_a_recuperer' => 4],
            ],
        ]);
        $uuid = $creation->json('data.uuid');

        $ajustement = $this->patchJson("/api/tournees/{$uuid}", [
            'statut' => 'en_cours',
            'lignes' => [
                ['depot_uuid' => $depot2->uuid, 'format_id' => $format->id, 'pleines' => 8, 'vides_a_recuperer' => 3],
            ],
        ]);

        $ajustement->assertOk();
        $ajustement->assertJsonPath('data.statut', 'en_cours');
        $ajustement->assertJsonCount(1, 'data.lignes');
        $ajustement->assertJsonPath('data.lignes.0.pleines', 8);
        $this->assertDatabaseCount('tournee_lignes', 1);
    }

    public function test_reculer_le_statut_d_une_tournee_est_refuse(): void
    {
        [$mandataire, $depot1] = $this->mandataireAvecDeuxDepots();
        $format = FormatBouteille::factory()->create();
        $gerant = $this->mandataireDe($mandataire);

        Sanctum::actingAs($gerant);
        $uuid = $this->postJson("/api/mandataires/{$mandataire->uuid}/tournees", [
            'date' => now()->toDateString(),
            'lignes' => [
                ['depot_uuid' => $depot1->uuid, 'format_id' => $format->id, 'pleines' => 1, 'vides_a_recuperer' => 0],
            ],
        ])->json('data.uuid');

        $this->patchJson("/api/tournees/{$uuid}", ['statut' => 'en_cours'])->assertOk();
        $this->patchJson("/api/tournees/{$uuid}", ['statut' => 'proposee'])->assertStatus(422);
    }

    public function test_un_autre_mandataire_ne_peut_pas_ajuster_une_tournee(): void
    {
        [$mandataireA, $depot1] = $this->mandataireAvecDeuxDepots();
        [$mandataireB] = $this->mandataireAvecDeuxDepots();
        $gerantA = $this->mandataireDe($mandataireA);
        $gerantB = $this->mandataireDe($mandataireB);
        $format = FormatBouteille::factory()->create();

        Sanctum::actingAs($gerantA);
        $uuid = $this->postJson("/api/mandataires/{$mandataireA->uuid}/tournees", [
            'date' => now()->toDateString(),
            'lignes' => [
                ['depot_uuid' => $depot1->uuid, 'format_id' => $format->id, 'pleines' => 1, 'vides_a_recuperer' => 0],
            ],
        ])->json('data.uuid');

        Sanctum::actingAs($gerantB);
        $this->patchJson("/api/tournees/{$uuid}", ['statut' => 'en_cours'])->assertNotFound();
    }
}

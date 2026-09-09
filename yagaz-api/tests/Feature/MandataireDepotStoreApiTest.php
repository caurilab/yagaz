<?php

namespace Tests\Feature;

use App\Enums\RoleMembership;
use App\Enums\TypeOrganisation;
use App\Models\Membership;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Création d'un dépôt par son mandataire (contrat API doc 11, §1,
 * `POST /api/mandataires/{orgUuid}/depots`) : rattachement au mandataire,
 * cloisonnement, validation.
 */
class MandataireDepotStoreApiTest extends TestCase
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

    public function test_le_mandataire_cree_un_depot_rattache_a_son_organisation(): void
    {
        $mandataire = Organisation::factory()->mandataire()->create();
        Sanctum::actingAs($this->mandataireDe($mandataire));

        $reponse = $this->postJson("/api/mandataires/{$mandataire->uuid}/depots", [
            'nom' => 'Dépôt Yopougon',
            'zone' => 'Yopougon',
        ]);

        $reponse->assertCreated()
            ->assertJsonPath('data.nom', 'Dépôt Yopougon')
            ->assertJsonPath('data.zone', 'Yopougon')
            ->assertJsonPath('data.en_tension', false)
            ->assertJsonPath('data.vides_a_recuperer', 0)
            ->assertJsonPath('data.stocks', []);

        $this->assertDatabaseHas('organisations', [
            'nom' => 'Dépôt Yopougon',
            'zone' => 'Yopougon',
            'type' => TypeOrganisation::Depot->value,
            'parent_id' => $mandataire->id,
        ]);

        // Le dépôt créé apparaît dans la liste consolidée du mandataire.
        $this->getJson("/api/mandataires/{$mandataire->uuid}/depots")
            ->assertOk()
            ->assertJsonFragment(['nom' => 'Dépôt Yopougon']);
    }

    public function test_un_autre_mandataire_ne_peut_pas_creer_de_depot_hors_perimetre(): void
    {
        $mandataire = Organisation::factory()->mandataire()->create();
        $autreMandataire = Organisation::factory()->mandataire()->create();
        Sanctum::actingAs($this->mandataireDe($autreMandataire));

        $this->postJson("/api/mandataires/{$mandataire->uuid}/depots", [
            'nom' => 'Dépôt pirate',
        ])->assertNotFound();

        $this->assertDatabaseMissing('organisations', ['nom' => 'Dépôt pirate']);
    }

    public function test_le_nom_est_obligatoire(): void
    {
        $mandataire = Organisation::factory()->mandataire()->create();
        Sanctum::actingAs($this->mandataireDe($mandataire));

        $this->postJson("/api/mandataires/{$mandataire->uuid}/depots", [
            'zone' => 'Zone sans nom',
        ])->assertStatus(422)->assertJsonValidationErrorFor('nom');
    }

    public function test_le_mandataire_cree_le_depot_avec_son_gerant_et_recoit_un_mot_de_passe_temporaire(): void
    {
        $mandataire = Organisation::factory()->mandataire()->create();
        Sanctum::actingAs($this->mandataireDe($mandataire));

        $reponse = $this->postJson("/api/mandataires/{$mandataire->uuid}/depots", [
            'nom' => 'Dépôt avec gérant',
            'zone' => 'Cocody',
            'gerant_nom' => 'Awa Koné',
            'gerant_telephone' => '+2250700000090',
        ]);

        $reponse->assertCreated()
            ->assertJsonPath('gerant.compte_cree', true)
            ->assertJsonPath('gerant.telephone', '+2250700000090');

        $motDePasse = $reponse->json('gerant.mot_de_passe_temporaire');
        $this->assertNotEmpty($motDePasse);

        $depot = Organisation::where('nom', 'Dépôt avec gérant')->firstOrFail();
        $gerant = User::where('telephone', '+2250700000090')->firstOrFail();
        $this->assertDatabaseHas('memberships', [
            'user_id' => $gerant->id,
            'organisation_id' => $depot->id,
            'role' => RoleMembership::GerantDepot->value,
            'actif' => true,
        ]);

        // Le gérant peut se connecter avec le mot de passe temporaire renvoyé.
        $this->postJson('/api/auth/login', [
            'telephone' => '+2250700000090',
            'mot_de_passe' => $motDePasse,
        ])->assertOk()->assertJsonPath('user.telephone', '+2250700000090');
    }

    public function test_un_compte_existant_est_rattache_comme_gerant_sans_mot_de_passe(): void
    {
        $mandataire = Organisation::factory()->mandataire()->create();
        Sanctum::actingAs($this->mandataireDe($mandataire));
        $existant = User::factory()->create(['telephone' => '+2250700000091']);

        $reponse = $this->postJson("/api/mandataires/{$mandataire->uuid}/depots", [
            'nom' => 'Dépôt gérant existant',
            'gerant_nom' => 'Peu importe',
            'gerant_telephone' => '+2250700000091',
        ]);

        $reponse->assertCreated()
            ->assertJsonPath('gerant.compte_cree', false)
            ->assertJsonPath('gerant.mot_de_passe_temporaire', null);

        $depot = Organisation::where('nom', 'Dépôt gérant existant')->firstOrFail();
        $this->assertDatabaseHas('memberships', [
            'user_id' => $existant->id,
            'organisation_id' => $depot->id,
            'role' => RoleMembership::GerantDepot->value,
        ]);
    }

    public function test_le_gerant_nom_est_requis_avec_le_telephone(): void
    {
        $mandataire = Organisation::factory()->mandataire()->create();
        Sanctum::actingAs($this->mandataireDe($mandataire));

        $this->postJson("/api/mandataires/{$mandataire->uuid}/depots", [
            'nom' => 'Dépôt',
            'gerant_telephone' => '+2250700000092',
        ])->assertStatus(422)->assertJsonValidationErrorFor('gerant_nom');
    }
}

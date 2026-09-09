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
}

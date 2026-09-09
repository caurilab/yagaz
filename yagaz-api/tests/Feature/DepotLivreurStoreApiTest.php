<?php

namespace Tests\Feature;

use App\Enums\RoleMembership;
use App\Models\Membership;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * POST /api/depots/{orgUuid}/livreurs : le gérant ajoute un livreur à son
 * équipe (provisioning descendant). Création/rattachement par téléphone,
 * cloisonnement, validation.
 */
class DepotLivreurStoreApiTest extends TestCase
{
    use RefreshDatabase;

    private function membre(Organisation $org, RoleMembership $role): User
    {
        $user = User::factory()->create();
        Membership::forceCreate([
            'user_id' => $user->id,
            'organisation_id' => $org->id,
            'role' => $role->value,
            'actif' => true,
        ]);

        return $user;
    }

    public function test_le_gerant_ajoute_un_livreur_et_recoit_un_mot_de_passe_temporaire(): void
    {
        $depot = Organisation::factory()->depot()->create();
        Sanctum::actingAs($this->membre($depot, RoleMembership::GerantDepot));

        $reponse = $this->postJson("/api/depots/{$depot->uuid}/livreurs", [
            'nom' => 'Moussa Livreur',
            'telephone' => '+2250700000080',
        ]);

        $reponse->assertCreated()
            ->assertJsonPath('compte_cree', true)
            ->assertJsonPath('data.nom', 'Moussa Livreur');

        $motDePasse = $reponse->json('mot_de_passe_temporaire');
        $this->assertNotEmpty($motDePasse);

        $livreur = User::where('telephone', '+2250700000080')->firstOrFail();
        $this->assertDatabaseHas('memberships', [
            'user_id' => $livreur->id,
            'organisation_id' => $depot->id,
            'role' => RoleMembership::Livreur->value,
            'actif' => true,
        ]);

        // Le nouveau livreur apparaît dans la liste et peut se connecter.
        $this->getJson("/api/depots/{$depot->uuid}/livreurs")
            ->assertOk()
            ->assertJsonFragment(['uuid' => $livreur->uuid]);
        $this->postJson('/api/auth/login', [
            'telephone' => '+2250700000080',
            'mot_de_passe' => $motDePasse,
        ])->assertOk();
    }

    public function test_un_livreur_ne_peut_pas_ajouter_de_livreur(): void
    {
        $depot = Organisation::factory()->depot()->create();
        Sanctum::actingAs($this->membre($depot, RoleMembership::Livreur));

        $this->postJson("/api/depots/{$depot->uuid}/livreurs", [
            'nom' => 'Intrus',
            'telephone' => '+2250700000081',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['telephone' => '+2250700000081']);
    }

    public function test_un_gerant_d_un_autre_depot_est_hors_perimetre(): void
    {
        $depot = Organisation::factory()->depot()->create();
        $autreDepot = Organisation::factory()->depot()->create();
        Sanctum::actingAs($this->membre($autreDepot, RoleMembership::GerantDepot));

        $this->postJson("/api/depots/{$depot->uuid}/livreurs", [
            'nom' => 'Livreur pirate',
            'telephone' => '+2250700000082',
        ])->assertNotFound();
    }

    public function test_le_telephone_est_obligatoire(): void
    {
        $depot = Organisation::factory()->depot()->create();
        Sanctum::actingAs($this->membre($depot, RoleMembership::GerantDepot));

        $this->postJson("/api/depots/{$depot->uuid}/livreurs", [
            'nom' => 'Sans téléphone',
        ])->assertStatus(422)->assertJsonValidationErrorFor('telephone');
    }
}

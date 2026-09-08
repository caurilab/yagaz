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
 * GET /api/depots/{orgUuid}/livreurs : liste des livreurs d'un dépôt pour
 * l'affectation d'une livraison (contrat doc 10, §4).
 */
class DepotLivreursApiTest extends TestCase
{
    use RefreshDatabase;

    private function membre(Organisation $org, RoleMembership $role, bool $actif = true): User
    {
        $user = User::factory()->create();
        Membership::forceCreate([
            'user_id' => $user->id,
            'organisation_id' => $org->id,
            'role' => $role->value,
            'actif' => $actif,
        ]);

        return $user;
    }

    public function test_le_gerant_voit_les_livreurs_actifs_de_son_depot(): void
    {
        $depot = Organisation::factory()->depot()->create();
        $gerant = $this->membre($depot, RoleMembership::GerantDepot);
        $livreur = $this->membre($depot, RoleMembership::Livreur);
        $this->membre($depot, RoleMembership::Livreur, actif: false); // inactif, exclu

        Sanctum::actingAs($gerant);

        $reponse = $this->getJson("/api/depots/{$depot->uuid}/livreurs");

        $reponse->assertOk();
        $reponse->assertJsonCount(1, 'data');
        $reponse->assertJsonPath('data.0.uuid', $livreur->uuid);
        // Projection minimale : pas de PII (email/telephone).
        $reponse->assertJsonMissingPath('data.0.email');
    }

    public function test_un_gerant_d_un_autre_depot_ne_voit_pas_les_livreurs(): void
    {
        $depot = Organisation::factory()->depot()->create();
        $this->membre($depot, RoleMembership::Livreur);

        $autreDepot = Organisation::factory()->depot()->create();
        $intrus = $this->membre($autreDepot, RoleMembership::GerantDepot);

        Sanctum::actingAs($intrus);

        // Hors périmètre : 404 (on ne révèle pas l'existence du dépôt).
        $this->getJson("/api/depots/{$depot->uuid}/livreurs")->assertNotFound();
    }

    public function test_un_livreur_n_est_pas_gerant_et_ne_peut_pas_lister(): void
    {
        $depot = Organisation::factory()->depot()->create();
        $livreur = $this->membre($depot, RoleMembership::Livreur);

        Sanctum::actingAs($livreur);

        $this->getJson("/api/depots/{$depot->uuid}/livreurs")->assertNotFound();
    }
}

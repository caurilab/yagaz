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
 * Mandataire — livreurs de tous ses dépôts (contrat API doc 11, §1,
 * `GET /api/mandataires/{orgUuid}/livreurs`), pour l'affectation d'une
 * tournée : cloisonnement à ses propres dépôts, seuls les livreurs actifs
 * apparaissent.
 */
class MandataireLivreursApiTest extends TestCase
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

    private function livreurDe(Organisation $organisation, bool $actif = true): User
    {
        $user = User::factory()->create();
        Membership::forceCreate([
            'user_id' => $user->id,
            'organisation_id' => $organisation->id,
            'role' => RoleMembership::Livreur->value,
            'actif' => $actif,
        ]);

        return $user;
    }

    /**
     * @return array{0: Organisation, 1: Organisation, 2: Organisation}
     */
    private function mandataireAvecDeuxDepots(): array
    {
        $mandataire = Organisation::factory()->mandataire()->create();
        $depot1 = Organisation::factory()->depot()->create(['parent_id' => $mandataire->id]);
        $depot2 = Organisation::factory()->depot()->create(['parent_id' => $mandataire->id]);

        return [$mandataire, $depot1, $depot2];
    }

    public function test_le_mandataire_recoit_les_livreurs_de_ses_depots(): void
    {
        [$mandataire, $depot1, $depot2] = $this->mandataireAvecDeuxDepots();
        $gerant = $this->mandataireDe($mandataire);
        $livreur1 = $this->livreurDe($depot1);
        $livreur2 = $this->livreurDe($depot2);

        Sanctum::actingAs($gerant);
        $reponse = $this->getJson("/api/mandataires/{$mandataire->uuid}/livreurs");

        $reponse->assertOk();
        $reponse->assertJsonCount(2, 'data');
        $reponse->assertJsonStructure(['data' => [['uuid', 'nom']]]);

        $noms = collect($reponse->json('data'))->pluck('nom');
        $this->assertTrue($noms->contains($livreur1->name));
        $this->assertTrue($noms->contains($livreur2->name));
    }

    public function test_un_autre_mandataire_ne_voit_pas_ces_livreurs(): void
    {
        [$mandataireA, $depotA] = $this->mandataireAvecDeuxDepots();
        [$mandataireB] = $this->mandataireAvecDeuxDepots();
        $this->livreurDe($depotA);
        $gerantB = $this->mandataireDe($mandataireB);

        Sanctum::actingAs($gerantB);

        $this->getJson("/api/mandataires/{$mandataireA->uuid}/livreurs")->assertNotFound();
    }

    public function test_un_livreur_inactif_n_apparait_pas(): void
    {
        [$mandataire, $depot1] = $this->mandataireAvecDeuxDepots();
        $gerant = $this->mandataireDe($mandataire);
        $this->livreurDe($depot1, actif: false);

        Sanctum::actingAs($gerant);
        $reponse = $this->getJson("/api/mandataires/{$mandataire->uuid}/livreurs");

        $reponse->assertOk();
        $reponse->assertJsonCount(0, 'data');
    }

    public function test_un_livreur_d_un_depot_d_un_autre_mandataire_n_apparait_pas(): void
    {
        [$mandataireA, $depotA] = $this->mandataireAvecDeuxDepots();
        [$mandataireB, $depotB] = $this->mandataireAvecDeuxDepots();
        $gerantA = $this->mandataireDe($mandataireA);
        $this->livreurDe($depotA);
        $this->livreurDe($depotB);

        Sanctum::actingAs($gerantA);
        $reponse = $this->getJson("/api/mandataires/{$mandataireA->uuid}/livreurs");

        $reponse->assertOk();
        $reponse->assertJsonCount(1, 'data');
    }
}

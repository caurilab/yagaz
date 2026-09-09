<?php

namespace Tests\Feature;

use App\Enums\NiveauAcces;
use App\Enums\StatutEquipement;
use App\Models\Equipement;
use App\Models\Plateau;
use App\Models\Site;
use App\Models\SiteAcces;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Équipements du registre unifié (contrat API, §« Équipements » - ADR 0012) :
 * CRUD, cloisonnement, unicité de la référence, réconciliation plateau.
 */
class EquipementApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Site}
     */
    private function foyerAvecSite(NiveauAcces $niveau = NiveauAcces::Proprietaire): array
    {
        $user = User::factory()->create();
        $site = Site::factory()->create(['cree_par' => $user->id]);
        SiteAcces::forceCreate([
            'site_id' => $site->id,
            'user_id' => $user->id,
            'niveau' => $niveau->value,
        ]);

        return [$user, $site];
    }

    public function test_le_proprietaire_enregistre_un_equipement_sur_son_site(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        Sanctum::actingAs($foyer);

        $reponse = $this->postJson('/api/equipements', [
            'type' => 'balance',
            'reference' => 'EQP-000001',
            'site_uuid' => $site->uuid,
        ]);

        $reponse->assertCreated();
        $reponse->assertJsonPath('data.type', 'balance');
        $reponse->assertJsonPath('data.reference', 'EQP-000001');
        $reponse->assertJsonPath('data.statut', 'a_connecter');
        $reponse->assertJsonPath('data.site.uuid', $site->uuid);

        $this->assertDatabaseHas('equipements', [
            'reference' => 'EQP-000001',
            'site_id' => $site->id,
            'cree_par' => $foyer->id,
        ]);
    }

    public function test_enregistrer_un_equipement_avec_un_nom(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        Sanctum::actingAs($foyer);

        $reponse = $this->postJson('/api/equipements', [
            'type' => 'balance',
            'reference' => 'EQP-000004',
            'nom' => 'Balance cuisine',
            'site_uuid' => $site->uuid,
        ]);

        $reponse->assertCreated();
        $reponse->assertJsonPath('data.nom', 'Balance cuisine');

        $this->assertDatabaseHas('equipements', [
            'reference' => 'EQP-000004',
            'nom' => 'Balance cuisine',
        ]);
    }

    public function test_enregistrer_sans_nom_le_laisse_nul(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        Sanctum::actingAs($foyer);

        $reponse = $this->postJson('/api/equipements', [
            'type' => 'balance',
            'reference' => 'EQP-000005',
            'site_uuid' => $site->uuid,
        ]);

        $reponse->assertCreated();
        $reponse->assertJsonPath('data.nom', null);
    }

    public function test_enregistrer_sans_site_cree_un_equipement_non_affecte(): void
    {
        $foyer = User::factory()->create();
        Sanctum::actingAs($foyer);

        $reponse = $this->postJson('/api/equipements', [
            'type' => 'temperature',
            'reference' => 'EQP-000002',
        ]);

        $reponse->assertCreated();
        $reponse->assertJsonPath('data.site', null);
        $reponse->assertJsonPath('data.statut', 'a_connecter');
    }

    public function test_la_reference_correspondant_a_un_plateau_provisionne_est_reliee_active(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        $plateau = Plateau::factory()->actif()->create(['site_id' => $site->id]);
        Sanctum::actingAs($foyer);

        $reponse = $this->postJson('/api/equipements', [
            'type' => 'balance',
            'reference' => $plateau->uid,
            'site_uuid' => $site->uuid,
        ]);

        $reponse->assertCreated();
        $reponse->assertJsonPath('data.statut', 'actif');
    }

    public function test_la_reference_dupliquee_est_refusee(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        Equipement::factory()->create(['reference' => 'EQP-DEJA-PRIS']);
        Sanctum::actingAs($foyer);

        $this->postJson('/api/equipements', [
            'type' => 'balance',
            'reference' => 'EQP-DEJA-PRIS',
            'site_uuid' => $site->uuid,
        ])->assertUnprocessable();
    }

    public function test_enregistrer_sur_un_site_hors_perimetre_est_refuse(): void
    {
        [, $siteAutrui] = $this->foyerAvecSite();
        $foyer = User::factory()->create();
        Sanctum::actingAs($foyer);

        $this->postJson('/api/equipements', [
            'type' => 'balance',
            'reference' => 'EQP-000003',
            'site_uuid' => $siteAutrui->uuid,
        ])->assertNotFound();

        $this->assertDatabaseMissing('equipements', ['reference' => 'EQP-000003']);
    }

    public function test_un_foyer_liste_les_equipements_de_ses_sites_et_les_siens_non_affectes(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        $affecte = Equipement::factory()->create(['site_id' => $site->id, 'cree_par' => $foyer->id]);
        $nonAffecteAMoi = Equipement::factory()->create(['site_id' => null, 'cree_par' => $foyer->id]);
        // Ni de mon site, ni créé par moi : ne doit pas apparaître.
        $autrui = User::factory()->create();
        Equipement::factory()->create(['site_id' => null, 'cree_par' => $autrui->id]);

        Sanctum::actingAs($foyer);

        $reponse = $this->getJson('/api/equipements');

        $reponse->assertOk();
        $reponse->assertJsonCount(2, 'data');
        $uuids = collect($reponse->json('data'))->pluck('uuid');
        $this->assertTrue($uuids->contains($affecte->uuid));
        $this->assertTrue($uuids->contains($nonAffecteAMoi->uuid));
    }

    public function test_un_foyer_ne_voit_pas_les_equipements_d_un_autre_foyer(): void
    {
        [, $siteA] = $this->foyerAvecSite();
        Equipement::factory()->create(['site_id' => $siteA->id]);

        [$foyerB] = $this->foyerAvecSite();
        Sanctum::actingAs($foyerB);

        $this->getJson('/api/equipements')->assertJsonCount(0, 'data');
    }

    public function test_affecter_un_equipement_non_affecte_a_un_site_accessible(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        $equipement = Equipement::factory()->create(['site_id' => null, 'cree_par' => $foyer->id]);

        Sanctum::actingAs($foyer);

        $reponse = $this->patchJson("/api/equipements/{$equipement->uuid}", [
            'site_uuid' => $site->uuid,
        ]);

        $reponse->assertOk();
        $reponse->assertJsonPath('data.site.uuid', $site->uuid);
        $this->assertSame($site->id, $equipement->fresh()->site_id);
    }

    public function test_affecter_a_un_site_hors_perimetre_est_refuse(): void
    {
        [$foyer] = $this->foyerAvecSite();
        [, $siteAutrui] = $this->foyerAvecSite();
        $equipement = Equipement::factory()->create(['site_id' => null, 'cree_par' => $foyer->id]);

        Sanctum::actingAs($foyer);

        $this->patchJson("/api/equipements/{$equipement->uuid}", [
            'site_uuid' => $siteAutrui->uuid,
        ])->assertNotFound();

        $this->assertNull($equipement->fresh()->site_id);
    }

    public function test_changer_le_statut_d_un_equipement(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        $equipement = Equipement::factory()->create([
            'site_id' => $site->id,
            'cree_par' => $foyer->id,
            'statut' => StatutEquipement::AConnecter,
        ]);

        Sanctum::actingAs($foyer);

        $reponse = $this->patchJson("/api/equipements/{$equipement->uuid}", [
            'statut' => 'hors_service',
        ]);

        $reponse->assertOk();
        $reponse->assertJsonPath('data.statut', 'hors_service');
    }

    public function test_modifier_le_nom_d_un_equipement(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        $equipement = Equipement::factory()->create([
            'site_id' => $site->id,
            'cree_par' => $foyer->id,
            'nom' => 'Ancien nom',
        ]);

        Sanctum::actingAs($foyer);

        $reponse = $this->patchJson("/api/equipements/{$equipement->uuid}", [
            'nom' => 'Balance cuisine',
        ]);

        $reponse->assertOk();
        $reponse->assertJsonPath('data.nom', 'Balance cuisine');
        $this->assertSame('Balance cuisine', $equipement->fresh()->nom);
    }

    public function test_retirer_l_affectation_avec_site_uuid_null(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        $equipement = Equipement::factory()->create(['site_id' => $site->id, 'cree_par' => $foyer->id]);

        Sanctum::actingAs($foyer);

        $reponse = $this->patchJson("/api/equipements/{$equipement->uuid}", [
            'site_uuid' => null,
        ]);

        $reponse->assertOk();
        $reponse->assertJsonPath('data.site', null);
        $this->assertNull($equipement->fresh()->site_id);
    }

    public function test_un_autre_foyer_ne_peut_pas_modifier_un_equipement_hors_perimetre(): void
    {
        [$foyerA, $siteA] = $this->foyerAvecSite();
        $equipement = Equipement::factory()->create(['site_id' => $siteA->id, 'cree_par' => $foyerA->id]);

        [$foyerB] = $this->foyerAvecSite();
        Sanctum::actingAs($foyerB);

        $this->patchJson("/api/equipements/{$equipement->uuid}", ['statut' => 'hors_service'])
            ->assertNotFound();
    }

    public function test_un_autre_utilisateur_ne_peut_pas_voir_un_equipement_non_affecte_d_un_tiers(): void
    {
        $createur = User::factory()->create();
        $equipement = Equipement::factory()->create(['site_id' => null, 'cree_par' => $createur->id]);

        $tiers = User::factory()->create();
        Sanctum::actingAs($tiers);

        $this->patchJson("/api/equipements/{$equipement->uuid}", ['statut' => 'hors_service'])
            ->assertNotFound();
    }

    public function test_supprime_un_equipement(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        $equipement = Equipement::factory()->create(['site_id' => $site->id, 'cree_par' => $foyer->id]);

        Sanctum::actingAs($foyer);

        $this->deleteJson("/api/equipements/{$equipement->uuid}")->assertNoContent();

        $this->assertDatabaseMissing('equipements', ['id' => $equipement->id]);
    }

    public function test_supprimer_un_equipement_hors_perimetre_est_refuse(): void
    {
        [, $siteA] = $this->foyerAvecSite();
        $equipement = Equipement::factory()->create(['site_id' => $siteA->id]);

        [$foyerB] = $this->foyerAvecSite();
        Sanctum::actingAs($foyerB);

        $this->deleteJson("/api/equipements/{$equipement->uuid}")->assertNotFound();
        $this->assertDatabaseHas('equipements', ['id' => $equipement->id]);
    }

    // === Capacités par site (`GET /api/sites`, ADR 0012) =================

    public function test_les_capacites_du_site_de_demo_sont_coherentes(): void
    {
        $this->seed();

        $foyer = User::where('telephone', '+221770000000')->firstOrFail();
        Sanctum::actingAs($foyer);

        $reponse = $this->getJson('/api/sites');

        $reponse->assertOk();
        $reponse->assertJsonPath('data.0.a_balance', true);
        $reponse->assertJsonPath('data.0.a_temperature', true);
        $reponse->assertJsonPath('data.0.a_ecran', false);
    }

    public function test_les_capacites_sont_fausses_sans_equipement_actif(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        // Équipement enregistré mais pas encore actif : ne doit pas ouvrir
        // la capacité.
        Equipement::factory()->create([
            'site_id' => $site->id,
            'cree_par' => $foyer->id,
            'statut' => StatutEquipement::AConnecter,
        ]);

        Sanctum::actingAs($foyer);

        $reponse = $this->getJson("/api/sites/{$site->uuid}");

        $reponse->assertOk();
        $reponse->assertJsonPath('data.a_balance', false);
        $reponse->assertJsonPath('data.a_temperature', false);
        $reponse->assertJsonPath('data.a_ecran', false);
    }

    public function test_la_capacite_balance_s_active_avec_un_equipement_actif(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        Equipement::factory()->actif()->create([
            'site_id' => $site->id,
            'cree_par' => $foyer->id,
        ]);

        Sanctum::actingAs($foyer);

        $reponse = $this->getJson("/api/sites/{$site->uuid}");

        $reponse->assertOk();
        $reponse->assertJsonPath('data.a_balance', true);
        $reponse->assertJsonPath('data.a_temperature', false);
    }
}

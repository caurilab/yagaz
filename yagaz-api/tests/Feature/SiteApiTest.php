<?php

namespace Tests\Feature;

use App\Enums\NiveauAcces;
use App\Models\Bouteille;
use App\Models\Site;
use App\Models\SiteAcces;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Sites du périmètre foyer (contrat API, §« Sites ») : cloisonnement HTTP et
 * partage d'accès.
 */
class SiteApiTest extends TestCase
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

    public function test_un_foyer_voit_ses_sites_avec_niveau_et_nb_bouteilles(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        Bouteille::factory()->create(['site_id' => $site->id]);

        Sanctum::actingAs($foyer);

        $reponse = $this->getJson('/api/sites');

        $reponse->assertOk();
        $reponse->assertJsonCount(1, 'data');
        $reponse->assertJsonPath('data.0.uuid', $site->uuid);
        $reponse->assertJsonPath('data.0.niveau_acces', 'proprietaire');
        $reponse->assertJsonPath('data.0.nb_bouteilles', 1);
        $reponse->assertJsonPath('data.0.a_alerte_active', false);
    }

    public function test_post_sites_cree_le_site_et_l_acces_proprietaire(): void
    {
        $foyer = User::factory()->create();
        Sanctum::actingAs($foyer);

        $reponse = $this->postJson('/api/sites', ['nom' => 'Maison']);

        $reponse->assertCreated();
        $reponse->assertJsonPath('data.niveau_acces', 'proprietaire');

        $site = Site::where('uuid', $reponse->json('data.uuid'))->firstOrFail();
        $this->assertSame($foyer->id, $site->cree_par);
        $this->assertDatabaseHas('site_acces', [
            'site_id' => $site->id,
            'user_id' => $foyer->id,
            'niveau' => NiveauAcces::Proprietaire->value,
        ]);
    }

    public function test_un_foyer_ne_peut_pas_voir_le_site_d_un_autre(): void
    {
        [, $siteA] = $this->foyerAvecSite();
        [$foyerB] = $this->foyerAvecSite();

        Sanctum::actingAs($foyerB);

        $this->getJson("/api/sites/{$siteA->uuid}")->assertNotFound();
    }

    public function test_un_observateur_voit_le_site_mais_ne_peut_pas_le_modifier(): void
    {
        [, $site] = $this->foyerAvecSite();
        $observateur = User::factory()->create();
        SiteAcces::forceCreate([
            'site_id' => $site->id,
            'user_id' => $observateur->id,
            'niveau' => NiveauAcces::Observateur->value,
        ]);

        Sanctum::actingAs($observateur);

        $this->getJson("/api/sites/{$site->uuid}")->assertOk();
        $this->patchJson("/api/sites/{$site->uuid}", ['nom' => 'Nouveau nom'])->assertForbidden();
    }

    public function test_le_proprietaire_partage_l_acces_a_un_utilisateur_existant(): void
    {
        [$proprietaire, $site] = $this->foyerAvecSite();
        $beneficiaire = User::factory()->create(['telephone' => '+221770001111']);

        Sanctum::actingAs($proprietaire);

        $reponse = $this->postJson("/api/sites/{$site->uuid}/partages", [
            'telephone' => '+221770001111',
            'niveau' => 'observateur',
        ]);

        $reponse->assertCreated();

        $this->assertDatabaseHas('site_acces', [
            'site_id' => $site->id,
            'user_id' => $beneficiaire->id,
            'niveau' => 'observateur',
        ]);

        // Le bénéficiaire accède désormais au site.
        Sanctum::actingAs($beneficiaire);
        $this->getJson("/api/sites/{$site->uuid}")->assertOk();
    }

    public function test_un_non_proprietaire_ne_peut_pas_partager_le_site(): void
    {
        [, $site] = $this->foyerAvecSite();
        $gestionnaire = User::factory()->create();
        SiteAcces::forceCreate([
            'site_id' => $site->id,
            'user_id' => $gestionnaire->id,
            'niveau' => NiveauAcces::Gestionnaire->value,
        ]);
        $cible = User::factory()->create(['telephone' => '+221770002222']);

        Sanctum::actingAs($gestionnaire);

        $this->postJson("/api/sites/{$site->uuid}/partages", [
            'telephone' => '+221770002222',
            'niveau' => 'observateur',
        ])->assertForbidden();

        $this->assertDatabaseMissing('site_acces', ['user_id' => $cible->id]);
    }

    public function test_le_proprietaire_retire_un_acces_partage(): void
    {
        [$proprietaire, $site] = $this->foyerAvecSite();
        $beneficiaire = User::factory()->create();
        SiteAcces::forceCreate([
            'site_id' => $site->id,
            'user_id' => $beneficiaire->id,
            'niveau' => NiveauAcces::Observateur->value,
        ]);

        Sanctum::actingAs($proprietaire);

        $this->deleteJson("/api/sites/{$site->uuid}/partages/{$beneficiaire->uuid}")->assertNoContent();

        $this->assertDatabaseMissing('site_acces', ['site_id' => $site->id, 'user_id' => $beneficiaire->id]);
    }
}

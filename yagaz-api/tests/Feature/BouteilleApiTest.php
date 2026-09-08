<?php

namespace Tests\Feature;

use App\Enums\NiveauAcces;
use App\Enums\RoleBouteille;
use App\Models\Bouteille;
use App\Models\FormatBouteille;
use App\Models\NiveauCourant;
use App\Models\Plateau;
use App\Models\Site;
use App\Models\SiteAcces;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Bouteilles du périmètre foyer (contrat API, §« Bouteilles ») : cloisonnement
 * HTTP, enregistrement, objet `niveau`, permutation active/secours.
 */
class BouteilleApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Site}
     */
    private function foyerAvecSite(): array
    {
        $user = User::factory()->create();
        $site = Site::factory()->create(['cree_par' => $user->id]);
        SiteAcces::forceCreate([
            'site_id' => $site->id,
            'user_id' => $user->id,
            'niveau' => NiveauAcces::Proprietaire->value,
        ]);

        return [$user, $site];
    }

    public function test_un_foyer_ne_peut_pas_voir_la_bouteille_d_un_autre(): void
    {
        [, $siteA] = $this->foyerAvecSite();
        $bouteilleA = Bouteille::factory()->create(['site_id' => $siteA->id]);

        [$foyerB] = $this->foyerAvecSite();
        Sanctum::actingAs($foyerB);

        $this->getJson("/api/bouteilles/{$bouteilleA->uuid}")->assertNotFound();
    }

    public function test_la_premiere_bouteille_du_site_devient_active_par_defaut(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        $format = FormatBouteille::factory()->create();

        Sanctum::actingAs($foyer);

        $reponse = $this->postJson("/api/sites/{$site->uuid}/bouteilles", [
            'format_id' => $format->id,
        ]);

        $reponse->assertCreated();
        $reponse->assertJsonPath('data.role_bouteille', 'active');
        $reponse->assertJsonPath('data.tare_source', 'nominale');
        $reponse->assertJsonPath('data.tare_fiable', false);

        // L'objet niveau est bien formé même sans mesure : état "inconnu".
        $reponse->assertJsonPath('data.niveau.etat', 'inconnu');
        $reponse->assertJsonPath('data.niveau.estimation', true);
        $reponse->assertJsonPath('data.niveau.frais', false);
        $reponse->assertJsonPath('data.niveau.gaz_g', null);
    }

    public function test_la_seconde_bouteille_du_site_est_secours_par_defaut(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        Bouteille::factory()->create(['site_id' => $site->id]); // active

        $format = FormatBouteille::factory()->create();
        Sanctum::actingAs($foyer);

        $reponse = $this->postJson("/api/sites/{$site->uuid}/bouteilles", [
            'format_id' => $format->id,
        ]);

        $reponse->assertCreated();
        $reponse->assertJsonPath('data.role_bouteille', 'secours');
    }

    public function test_enregistrement_avec_tare_saisie_et_plateau(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        $format = FormatBouteille::factory()->create();
        $plateau = Plateau::factory()->actif()->create(['site_id' => $site->id]);

        Sanctum::actingAs($foyer);

        $reponse = $this->postJson("/api/sites/{$site->uuid}/bouteilles", [
            'format_id' => $format->id,
            'tare_g' => 13200,
            'plateau_uid' => $plateau->uid,
        ]);

        $reponse->assertCreated();
        $reponse->assertJsonPath('data.tare_g', 13200);
        $reponse->assertJsonPath('data.tare_source', 'saisie');
        $reponse->assertJsonPath('data.tare_fiable', true);
        $reponse->assertJsonPath('data.plateau_uid', $plateau->uid);
    }

    public function test_le_niveau_courant_est_expose_avec_un_etat_coherent(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        $format = FormatBouteille::factory()->create([
            'tare_nominale_g' => 13000,
            'contenance_gaz_g' => 12500,
        ]);
        $bouteille = Bouteille::factory()->create([
            'site_id' => $site->id,
            'format_id' => $format->id,
            'seuil_bas_pct' => 15,
        ]);

        NiveauCourant::create([
            'bouteille_id' => $bouteille->id,
            'gaz_g' => 8400,
            'niveau_pct' => 67,
            'autonomie_min' => 3360,
            'debit_g_par_h' => 150,
            'calcule_at' => now(),
        ]);

        Sanctum::actingAs($foyer);

        $reponse = $this->getJson("/api/bouteilles/{$bouteille->uuid}");

        $reponse->assertOk();
        $reponse->assertJsonPath('data.niveau.gaz_g', 8400);
        $reponse->assertJsonPath('data.niveau.niveau_pct', 67);
        $reponse->assertJsonPath('data.niveau.etat', 'correct');
        $reponse->assertJsonPath('data.niveau.autonomie_min', 3360);
        $reponse->assertJsonPath('data.niveau.autonomie_heures', 56);
        $reponse->assertJsonPath('data.niveau.frais', true);
    }

    public function test_patch_role_bouteille_active_permute_avec_l_ancienne_active(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        $active = Bouteille::factory()->create(['site_id' => $site->id]);
        $secours = Bouteille::factory()->secours()->create(['site_id' => $site->id]);

        Sanctum::actingAs($foyer);

        $reponse = $this->patchJson("/api/bouteilles/{$secours->uuid}", [
            'role_bouteille' => 'active',
        ]);

        $reponse->assertOk();
        $reponse->assertJsonPath('data.role_bouteille', 'active');

        $this->assertSame(RoleBouteille::Secours, $active->fresh()->role_bouteille);
        $this->assertSame(RoleBouteille::Active, $secours->fresh()->role_bouteille);

        // Une seule bouteille active par site : pas de violation d'unique.
        $this->assertSame(1, Bouteille::where('site_id', $site->id)
            ->where('role_bouteille', RoleBouteille::Active->value)
            ->count());
    }

    public function test_un_observateur_ne_peut_pas_modifier_une_bouteille(): void
    {
        [, $site] = $this->foyerAvecSite();
        $bouteille = Bouteille::factory()->create(['site_id' => $site->id]);
        $observateur = User::factory()->create();
        SiteAcces::forceCreate([
            'site_id' => $site->id,
            'user_id' => $observateur->id,
            'niveau' => NiveauAcces::Observateur->value,
        ]);

        Sanctum::actingAs($observateur);

        $this->patchJson("/api/bouteilles/{$bouteille->uuid}", ['seuil_bas_pct' => 20])->assertForbidden();
    }

    public function test_lier_puis_delier_un_plateau(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        $bouteille = Bouteille::factory()->create(['site_id' => $site->id]);
        $plateau = Plateau::factory()->actif()->create(['site_id' => $site->id]);

        Sanctum::actingAs($foyer);

        $this->postJson("/api/bouteilles/{$bouteille->uuid}/plateau", ['plateau_uid' => $plateau->uid])
            ->assertOk()
            ->assertJsonPath('data.plateau_uid', $plateau->uid);

        $this->deleteJson("/api/bouteilles/{$bouteille->uuid}/plateau")
            ->assertOk()
            ->assertJsonPath('data.plateau_uid', null);
    }

    public function test_le_site_id_injecte_dans_le_corps_est_ignore(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        [, $autreSite] = $this->foyerAvecSite();
        $format = FormatBouteille::factory()->create();

        Sanctum::actingAs($foyer);

        $reponse = $this->postJson("/api/sites/{$site->uuid}/bouteilles", [
            'format_id' => $format->id,
            // Tentative de mass-assignment : la bouteille doit être créée
            // sur le site de l'URL, jamais sur celui-ci (audit sécurité,
            // [INFO] $fillable explicite).
            'site_id' => $autreSite->id,
        ]);

        $reponse->assertCreated();

        $bouteille = Bouteille::where('uuid', $reponse->json('data.uuid'))->firstOrFail();
        $this->assertSame($site->id, $bouteille->site_id);
        $this->assertNotSame($autreSite->id, $bouteille->site_id);
    }

    public function test_le_plateau_d_un_autre_site_est_refuse(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        [, $autreSite] = $this->foyerAvecSite();
        $bouteille = Bouteille::factory()->create(['site_id' => $site->id]);
        $plateauAutreSite = Plateau::factory()->actif()->create(['site_id' => $autreSite->id]);

        Sanctum::actingAs($foyer);

        $this->postJson("/api/bouteilles/{$bouteille->uuid}/plateau", [
            'plateau_uid' => $plateauAutreSite->uid,
        ])->assertUnprocessable();

        $format = FormatBouteille::factory()->create();
        $this->postJson("/api/sites/{$site->uuid}/bouteilles", [
            'format_id' => $format->id,
            'plateau_uid' => $plateauAutreSite->uid,
        ])->assertUnprocessable();
    }

    public function test_le_plateau_non_actif_est_refuse(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        $bouteille = Bouteille::factory()->create(['site_id' => $site->id]);
        $plateauInactif = Plateau::factory()->create(['site_id' => $site->id]);

        Sanctum::actingAs($foyer);

        $this->postJson("/api/bouteilles/{$bouteille->uuid}/plateau", [
            'plateau_uid' => $plateauInactif->uid,
        ])->assertUnprocessable();
    }

    public function test_supprime_une_bouteille(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        $bouteille = Bouteille::factory()->secours()->create(['site_id' => $site->id]);

        Sanctum::actingAs($foyer);

        $this->deleteJson("/api/bouteilles/{$bouteille->uuid}")->assertNoContent();

        $this->assertDatabaseMissing('bouteilles', ['id' => $bouteille->id]);
    }
}

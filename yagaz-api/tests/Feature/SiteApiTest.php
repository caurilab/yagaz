<?php

namespace Tests\Feature;

use App\Enums\NiveauAcces;
use App\Enums\RoleMembership;
use App\Models\Bouteille;
use App\Models\FormatBouteille;
use App\Models\LivreurHabituel;
use App\Models\Membership;
use App\Models\Organisation;
use App\Models\Plateau;
use App\Models\Site;
use App\Models\SiteAcces;
use App\Models\User;
use App\Services\Mesure\TraitementMesure;
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

    public function test_patch_site_ignore_cree_par_injecte(): void
    {
        [$proprietaire, $site] = $this->foyerAvecSite();
        $intrus = User::factory()->create();

        Sanctum::actingAs($proprietaire);

        $reponse = $this->patchJson("/api/sites/{$site->uuid}", [
            'nom' => 'Nouveau nom',
            // Tentative de mass-assignment : ignoré (audit sécurité, [INFO]
            // $fillable explicite).
            'cree_par' => $intrus->id,
        ]);

        $reponse->assertOk();
        $this->assertSame($proprietaire->id, $site->fresh()->cree_par);
        $this->assertSame('Nouveau nom', $site->fresh()->nom);
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
        $reponse->assertJsonStructure(['message', 'utilisateur' => ['uuid', 'nom'], 'niveau']);
        // Projection minimale du bénéficiaire pour un tiers (audit sécurité,
        // [FAIBLE] fuite de PII) : ni email ni réglages/livreur.
        $this->assertArrayNotHasKey('email', $reponse->json('utilisateur'));
        $this->assertArrayNotHasKey('reglages_alertes', $reponse->json('utilisateur'));
        $this->assertArrayNotHasKey('telephone', $reponse->json('utilisateur'));

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

    public function test_impossible_de_retirer_le_dernier_proprietaire(): void
    {
        [$proprietaire, $site] = $this->foyerAvecSite();

        Sanctum::actingAs($proprietaire);

        $reponse = $this->deleteJson("/api/sites/{$site->uuid}/partages/{$proprietaire->uuid}");

        $reponse->assertStatus(422);
        $this->assertDatabaseHas('site_acces', [
            'site_id' => $site->id,
            'user_id' => $proprietaire->id,
            'niveau' => NiveauAcces::Proprietaire->value,
        ]);
    }

    public function test_impossible_de_retrograder_le_dernier_proprietaire(): void
    {
        [$proprietaire, $site] = $this->foyerAvecSite();
        $proprietaire->forceFill(['telephone' => '+221770005555'])->save();

        Sanctum::actingAs($proprietaire);

        $reponse = $this->postJson("/api/sites/{$site->uuid}/partages", [
            'telephone' => '+221770005555',
            'niveau' => 'observateur',
        ]);

        $reponse->assertStatus(422);
        $this->assertDatabaseHas('site_acces', [
            'site_id' => $site->id,
            'user_id' => $proprietaire->id,
            'niveau' => NiveauAcces::Proprietaire->value,
        ]);
    }

    public function test_un_second_proprietaire_peut_etre_retire(): void
    {
        [$proprietaire, $site] = $this->foyerAvecSite();
        $second = User::factory()->create();
        SiteAcces::forceCreate([
            'site_id' => $site->id,
            'user_id' => $second->id,
            'niveau' => NiveauAcces::Proprietaire->value,
        ]);

        Sanctum::actingAs($proprietaire);

        $this->deleteJson("/api/sites/{$site->uuid}/partages/{$second->uuid}")->assertNoContent();

        $this->assertDatabaseMissing('site_acces', ['site_id' => $site->id, 'user_id' => $second->id]);
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

    // === Livreur habituel PAR SITE (correctif [IMPORTANT], ADR 0009) ======

    private function livreur(string $telephone): User
    {
        $user = User::factory()->create(['telephone' => $telephone]);
        Membership::forceCreate([
            'user_id' => $user->id,
            'organisation_id' => Organisation::factory()->depot()->create()->id,
            'role' => RoleMembership::Livreur->value,
            'actif' => true,
        ]);

        return $user;
    }

    public function test_le_proprietaire_designe_un_livreur_habituel_pour_le_site(): void
    {
        [$proprietaire, $site] = $this->foyerAvecSite();
        $livreur = $this->livreur('+221770009999');

        Sanctum::actingAs($proprietaire);

        $reponse = $this->postJson("/api/sites/{$site->uuid}/livreur-habituel", [
            'telephone' => '+221770009999',
        ]);

        $reponse->assertCreated();
        $this->assertDatabaseHas('livreur_habituel', [
            'site_id' => $site->id,
            'livreur_user_id' => $livreur->id,
            'actif' => true,
        ]);
    }

    public function test_designer_remplace_le_livreur_habituel_precedent(): void
    {
        [$proprietaire, $site] = $this->foyerAvecSite();
        $premier = $this->livreur('+221770001010');
        $second = $this->livreur('+221770002020');

        LivreurHabituel::create(['site_id' => $site->id, 'livreur_user_id' => $premier->id, 'actif' => true]);

        Sanctum::actingAs($proprietaire);
        $this->postJson("/api/sites/{$site->uuid}/livreur-habituel", ['telephone' => '+221770002020'])
            ->assertCreated();

        // Un seul livreur habituel actif par site (site_id unique).
        $this->assertSame(1, LivreurHabituel::where('site_id', $site->id)->count());
        $this->assertDatabaseHas('livreur_habituel', ['site_id' => $site->id, 'livreur_user_id' => $second->id]);
    }

    public function test_un_non_proprietaire_ne_peut_pas_designer_le_livreur_habituel(): void
    {
        [, $site] = $this->foyerAvecSite();
        $gestionnaire = User::factory()->create();
        SiteAcces::forceCreate([
            'site_id' => $site->id,
            'user_id' => $gestionnaire->id,
            'niveau' => NiveauAcces::Gestionnaire->value,
        ]);
        $livreur = $this->livreur('+221770003030');

        Sanctum::actingAs($gestionnaire);

        $this->postJson("/api/sites/{$site->uuid}/livreur-habituel", ['telephone' => '+221770003030'])
            ->assertForbidden();

        $this->assertDatabaseMissing('livreur_habituel', ['site_id' => $site->id, 'livreur_user_id' => $livreur->id]);
    }

    public function test_une_cible_non_livreur_est_refusee(): void
    {
        [$proprietaire, $site] = $this->foyerAvecSite();
        $nonLivreur = User::factory()->create(['telephone' => '+221770004040']);

        Sanctum::actingAs($proprietaire);

        $this->postJson("/api/sites/{$site->uuid}/livreur-habituel", ['telephone' => '+221770004040'])
            ->assertStatus(422);

        $this->assertDatabaseMissing('livreur_habituel', ['site_id' => $site->id, 'livreur_user_id' => $nonLivreur->id]);
    }

    public function test_le_proprietaire_retire_la_designation_du_livreur_habituel(): void
    {
        [$proprietaire, $site] = $this->foyerAvecSite();
        $livreur = $this->livreur('+221770005050');
        LivreurHabituel::create(['site_id' => $site->id, 'livreur_user_id' => $livreur->id, 'actif' => true]);

        Sanctum::actingAs($proprietaire);

        $this->deleteJson("/api/sites/{$site->uuid}/livreur-habituel")->assertNoContent();

        $this->assertDatabaseMissing('livreur_habituel', ['site_id' => $site->id]);
    }

    /**
     * Bout-en-bout : après désignation via l'endpoint (pas de fixture
     * directe en base), le maillon A (ADR 0009) notifie bien ce livreur au
     * franchissement du seuil bas — preuve que la désignation par ce point
     * d'entrée câble effectivement le déclenchement automatique.
     */
    public function test_apres_designation_via_l_endpoint_le_livreur_est_notifie_au_seuil_bas(): void
    {
        [$proprietaire, $site] = $this->foyerAvecSite();
        $livreur = $this->livreur('+221770006060');

        Sanctum::actingAs($proprietaire);
        $this->postJson("/api/sites/{$site->uuid}/livreur-habituel", ['telephone' => '+221770006060'])
            ->assertCreated();

        $plateau = Plateau::factory()->actif()->create(['site_id' => $site->id]);
        $format = FormatBouteille::factory()->create(['tare_nominale_g' => 13000, 'contenance_gaz_g' => 12500]);
        Bouteille::factory()->create([
            'site_id' => $site->id,
            'plateau_id' => $plateau->id,
            'format_id' => $format->id,
            'tare_g' => 13000,
            'tare_fiable' => true,
            'seuil_bas_pct' => 15,
        ]);

        $service = new TraitementMesure;
        $seq = 1;
        $service->traiter(['uid' => $plateau->uid, 'v' => 1, 'ts' => now()->timestamp, 'poids_g' => 19250, 'seq' => $seq++]);
        for ($i = 0; $i < 6; $i++) {
            $service->traiter(['uid' => $plateau->uid, 'v' => 1, 'ts' => now()->timestamp, 'poids_g' => 13625, 'seq' => $seq++]);
        }

        Sanctum::actingAs($livreur);
        $notifications = $this->getJson('/api/notifications');
        $notifications->assertOk();
        $notifications->assertJsonCount(1, 'data');
        $notifications->assertJsonPath('data.0.type', 'seuil_bas');
    }
}

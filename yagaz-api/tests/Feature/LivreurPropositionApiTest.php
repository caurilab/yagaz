<?php

namespace Tests\Feature;

use App\Enums\NiveauAcces;
use App\Enums\RoleBouteille;
use App\Enums\RoleMembership;
use App\Models\Bouteille;
use App\Models\FormatBouteille;
use App\Models\LivreurHabituel;
use App\Models\Membership;
use App\Models\NiveauCourant;
use App\Models\Organisation;
use App\Models\Site;
use App\Models\SiteAcces;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * ADR 0009, maillon C — `POST /api/livreur/propositions` : le livreur
 * habituel d'un site en tension propose une livraison. Autorisation
 * tracée : uniquement pour ses foyers habituels en tension.
 */
class LivreurPropositionApiTest extends TestCase
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

    private function livreurDe(Organisation $depot): User
    {
        $user = User::factory()->create();
        Membership::forceCreate([
            'user_id' => $user->id,
            'organisation_id' => $depot->id,
            'role' => RoleMembership::Livreur->value,
            'actif' => true,
        ]);

        return $user;
    }

    /**
     * Rend le site en tension : une bouteille active dont le niveau courant
     * est sous son seuil bas.
     */
    private function mettreEnTension(Site $site, ?FormatBouteille $format = null): Bouteille
    {
        $bouteille = Bouteille::factory()->create([
            'site_id' => $site->id,
            'format_id' => ($format ?? FormatBouteille::factory()->create())->id,
            'role_bouteille' => RoleBouteille::Active,
            'seuil_bas_pct' => 15,
        ]);
        NiveauCourant::create([
            'bouteille_id' => $bouteille->id,
            'gaz_g' => 0,
            'niveau_pct' => 5,
            'autonomie_min' => 0,
            'debit_g_par_h' => 100,
            'calcule_at' => now(),
        ]);

        return $bouteille;
    }

    /**
     * Site avec bouteille active MAIS niveau au-dessus du seuil (pas en
     * tension).
     */
    private function sansTension(Site $site): void
    {
        $bouteille = Bouteille::factory()->create([
            'site_id' => $site->id,
            'role_bouteille' => RoleBouteille::Active,
            'seuil_bas_pct' => 15,
        ]);
        NiveauCourant::create([
            'bouteille_id' => $bouteille->id,
            'gaz_g' => 8000,
            'niveau_pct' => 80,
            'autonomie_min' => 1000,
            'debit_g_par_h' => 100,
            'calcule_at' => now(),
        ]);
    }

    public function test_le_livreur_habituel_d_un_site_en_tension_peut_proposer_et_le_foyer_accepte(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        $depot = Organisation::factory()->depot()->create();
        $livreur = $this->livreurDe($depot);
        $this->mettreEnTension($site);

        LivreurHabituel::create([
            'site_id' => $site->id,
            'livreur_user_id' => $livreur->id,
            'actif' => true,
        ]);

        Sanctum::actingAs($livreur);
        $proposition = $this->postJson('/api/livreur/propositions', [
            'site_uuid' => $site->uuid,
        ]);

        $proposition->assertCreated();
        $proposition->assertJsonPath('data.statut', 'proposee');
        $proposition->assertJsonPath('data.origine', 'depot');
        $this->assertDatabaseHas('commandes', [
            'uuid' => $proposition->json('data.uuid'),
            'cible_org_id' => $depot->id,
            'site_id' => $site->id,
            'statut' => 'proposee',
        ]);

        // Le foyer confirme ensuite (mécanisme existant).
        $uuid = $proposition->json('data.uuid');
        Sanctum::actingAs($foyer);
        $reponse = $this->postJson("/api/commandes/{$uuid}/reponse", ['accepte' => true]);
        $reponse->assertOk();
        $reponse->assertJsonPath('data.statut', 'confirmee');
    }

    public function test_un_livreur_non_habituel_du_site_est_refuse(): void
    {
        [, $site] = $this->foyerAvecSite();
        $depot = Organisation::factory()->depot()->create();
        $livreurNonHabituel = $this->livreurDe($depot);
        $this->mettreEnTension($site);

        // Aucun LivreurHabituel désigné pour ce site.

        Sanctum::actingAs($livreurNonHabituel);
        $this->postJson('/api/livreur/propositions', [
            'site_uuid' => $site->uuid,
        ])->assertForbidden();

        $this->assertDatabaseCount('commandes', 0);
    }

    public function test_un_livreur_habituel_d_un_autre_site_est_refuse_pour_celui_ci(): void
    {
        [, $siteA] = $this->foyerAvecSite();
        [, $siteB] = $this->foyerAvecSite();
        $depot = Organisation::factory()->depot()->create();
        $livreur = $this->livreurDe($depot);
        $this->mettreEnTension($siteA);
        $this->mettreEnTension($siteB);

        // Livreur habituel de B, pas de A.
        LivreurHabituel::create([
            'site_id' => $siteB->id,
            'livreur_user_id' => $livreur->id,
            'actif' => true,
        ]);

        Sanctum::actingAs($livreur);
        $this->postJson('/api/livreur/propositions', [
            'site_uuid' => $siteA->uuid,
        ])->assertForbidden();
    }

    public function test_proposer_pour_un_site_non_en_tension_est_refuse(): void
    {
        [, $site] = $this->foyerAvecSite();
        $depot = Organisation::factory()->depot()->create();
        $livreur = $this->livreurDe($depot);
        $this->sansTension($site);

        LivreurHabituel::create([
            'site_id' => $site->id,
            'livreur_user_id' => $livreur->id,
            'actif' => true,
        ]);

        Sanctum::actingAs($livreur);
        $this->postJson('/api/livreur/propositions', [
            'site_uuid' => $site->uuid,
        ])->assertStatus(422);

        $this->assertDatabaseCount('commandes', 0);
    }

    public function test_site_inconnu_renvoie_404(): void
    {
        $depot = Organisation::factory()->depot()->create();
        $livreur = $this->livreurDe($depot);

        Sanctum::actingAs($livreur);
        $this->postJson('/api/livreur/propositions', [
            'site_uuid' => (string) Str::uuid(),
        ])->assertNotFound();
    }

    public function test_un_livreur_de_plusieurs_depots_doit_preciser_le_depot_cible(): void
    {
        [, $site] = $this->foyerAvecSite();
        $depotA = Organisation::factory()->depot()->create();
        $depotB = Organisation::factory()->depot()->create();
        $livreur = User::factory()->create();
        Membership::forceCreate(['user_id' => $livreur->id, 'organisation_id' => $depotA->id, 'role' => RoleMembership::Livreur->value, 'actif' => true]);
        Membership::forceCreate(['user_id' => $livreur->id, 'organisation_id' => $depotB->id, 'role' => RoleMembership::Livreur->value, 'actif' => true]);
        $this->mettreEnTension($site);

        LivreurHabituel::create([
            'site_id' => $site->id,
            'livreur_user_id' => $livreur->id,
            'actif' => true,
        ]);

        Sanctum::actingAs($livreur);

        // Sans depot_uuid : ambigu, refusé.
        $this->postJson('/api/livreur/propositions', ['site_uuid' => $site->uuid])->assertStatus(422);

        // Avec depot_uuid : accepté, ciblant le bon dépôt.
        $proposition = $this->postJson('/api/livreur/propositions', [
            'site_uuid' => $site->uuid,
            'depot_uuid' => $depotB->uuid,
        ]);
        $proposition->assertCreated();
        $this->assertDatabaseHas('commandes', [
            'uuid' => $proposition->json('data.uuid'),
            'cible_org_id' => $depotB->id,
        ]);
    }
}

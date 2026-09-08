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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * ADR 0008, précision « maillon C » — `GET /api/livreur/foyers-en-tension` :
 * la file actionnable du livreur habituel expose l'identité de SES foyers
 * habituels en tension, et rien de plus. Test d'étanchéité dédié : la
 * réponse ne porte ni niveau, ni autonomie, ni historique, ni autres
 * bouteilles/sites, ni contact.
 */
class LivreurFoyersEnTensionApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Site}
     */
    private function foyerAvecSite(string $zone = 'Plateau'): array
    {
        $user = User::factory()->create();
        $site = Site::factory()->create(['cree_par' => $user->id, 'zone' => $zone]);
        SiteAcces::forceCreate([
            'site_id' => $site->id,
            'user_id' => $user->id,
            'niveau' => NiveauAcces::Proprietaire->value,
        ]);

        return [$user, $site];
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
    private function sansTension(Site $site, ?FormatBouteille $format = null): void
    {
        $bouteille = Bouteille::factory()->create([
            'site_id' => $site->id,
            'format_id' => ($format ?? FormatBouteille::factory()->create())->id,
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

    public function test_la_file_ne_renvoie_que_les_foyers_habituels_du_livreur_en_tension(): void
    {
        $livreur = User::factory()->create();
        $format = FormatBouteille::factory()->create(['code' => 'B12']);

        // Foyer habituel du livreur, en tension : inclus.
        [, $siteHabituelEnTension] = $this->foyerAvecSite(zone: 'Almadies');
        $this->mettreEnTension($siteHabituelEnTension, $format);
        LivreurHabituel::create([
            'site_id' => $siteHabituelEnTension->id,
            'livreur_user_id' => $livreur->id,
            'actif' => true,
        ]);

        // Foyer habituel du livreur, PAS en tension : exclu.
        [, $siteHabituelHorsTension] = $this->foyerAvecSite(zone: 'Ngor');
        $this->sansTension($siteHabituelHorsTension, $format);
        LivreurHabituel::create([
            'site_id' => $siteHabituelHorsTension->id,
            'livreur_user_id' => $livreur->id,
            'actif' => true,
        ]);

        // En tension, mais livreur habituel d'un AUTRE livreur : exclu.
        [, $siteAutreLivreur] = $this->foyerAvecSite(zone: 'Yoff');
        $this->mettreEnTension($siteAutreLivreur, $format);
        $autreLivreur = User::factory()->create();
        LivreurHabituel::create([
            'site_id' => $siteAutreLivreur->id,
            'livreur_user_id' => $autreLivreur->id,
            'actif' => true,
        ]);

        // En tension, aucun livreur habituel désigné : exclu.
        [, $siteSansLivreur] = $this->foyerAvecSite(zone: 'Ouakam');
        $this->mettreEnTension($siteSansLivreur, $format);

        Sanctum::actingAs($livreur);
        $reponse = $this->getJson('/api/livreur/foyers-en-tension');

        $reponse->assertOk();
        $reponse->assertJsonCount(1, 'data');
        $reponse->assertJsonPath('data.0.site_uuid', $siteHabituelEnTension->uuid);
        $reponse->assertJsonPath('data.0.zone', 'Almadies');
        $reponse->assertJsonPath('data.0.format.code', 'B12');
    }

    public function test_un_livreur_habituel_inactif_ne_voit_pas_le_site_dans_la_file(): void
    {
        $livreur = User::factory()->create();
        [, $site] = $this->foyerAvecSite();
        $this->mettreEnTension($site);
        LivreurHabituel::create([
            'site_id' => $site->id,
            'livreur_user_id' => $livreur->id,
            'actif' => false,
        ]);

        Sanctum::actingAs($livreur);
        $this->getJson('/api/livreur/foyers-en-tension')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * Test d'étanchéité dédié (ADR 0008) : la file du livreur ne porte ni
     * niveau exact, ni autonomie, ni historique, ni les autres bouteilles/
     * sites du foyer, ni contact — seulement site_uuid, nom, zone, format.
     */
    public function test_etancheite_la_file_ne_fuite_aucune_donnee_au_dela_de_l_identite_minimale(): void
    {
        $livreur = User::factory()->create();
        [$foyer, $site] = $this->foyerAvecSite(zone: 'Plateau');
        $format = FormatBouteille::factory()->create(['code' => 'B6']);
        $this->mettreEnTension($site, $format);
        LivreurHabituel::create([
            'site_id' => $site->id,
            'livreur_user_id' => $livreur->id,
            'actif' => true,
        ]);

        // Une autre bouteille (inactive) sur le même site, et un autre site
        // du même foyer : ne doivent jamais apparaître dans la réponse.
        Bouteille::factory()->secours()->create([
            'site_id' => $site->id,
        ]);
        $autreSiteDuFoyer = Site::factory()->create(['cree_par' => $foyer->id, 'nom' => 'Boutique secrète']);
        SiteAcces::forceCreate([
            'site_id' => $autreSiteDuFoyer->id,
            'user_id' => $foyer->id,
            'niveau' => NiveauAcces::Proprietaire->value,
        ]);

        Sanctum::actingAs($livreur);
        $reponse = $this->getJson('/api/livreur/foyers-en-tension');
        $reponse->assertOk();
        $reponse->assertJsonCount(1, 'data');

        $entree = $reponse->json('data.0');
        $this->assertEqualsCanonicalizing(['site_uuid', 'nom', 'zone', 'format'], array_keys($entree));
        $this->assertSame($site->uuid, $entree['site_uuid']);
        $this->assertSame($site->nom, $entree['nom']);
        $this->assertSame('Plateau', $entree['zone']);
        $this->assertSame('B6', $entree['format']['code']);

        $texteBrut = (string) $reponse->getContent();
        $this->assertStringNotContainsString('niveau_pct', $texteBrut);
        $this->assertStringNotContainsString('autonomie', $texteBrut);
        $this->assertStringNotContainsString('telephone', $texteBrut);
        $this->assertStringNotContainsString('Boutique secrète', $texteBrut);
        $this->assertStringNotContainsString($autreSiteDuFoyer->uuid, $texteBrut);
    }

    public function test_le_livreur_propose_ensuite_via_le_site_uuid_de_la_file(): void
    {
        $livreur = User::factory()->create();
        $depot = Organisation::factory()->depot()->create();
        Membership::forceCreate([
            'user_id' => $livreur->id,
            'organisation_id' => $depot->id,
            'role' => RoleMembership::Livreur->value,
            'actif' => true,
        ]);
        [, $site] = $this->foyerAvecSite();
        $this->mettreEnTension($site);
        LivreurHabituel::create([
            'site_id' => $site->id,
            'livreur_user_id' => $livreur->id,
            'actif' => true,
        ]);

        Sanctum::actingAs($livreur);
        $file = $this->getJson('/api/livreur/foyers-en-tension');
        $file->assertOk();
        $siteUuid = $file->json('data.0.site_uuid');

        $proposition = $this->postJson('/api/livreur/propositions', [
            'site_uuid' => $siteUuid,
        ]);
        $proposition->assertCreated();
        $proposition->assertJsonPath('data.statut', 'proposee');
        $this->assertDatabaseHas('commandes', [
            'uuid' => $proposition->json('data.uuid'),
            'site_id' => $site->id,
            'statut' => 'proposee',
        ]);
    }
}

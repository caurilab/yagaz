<?php

namespace Tests\Feature;

use App\Enums\NiveauAcces;
use App\Models\Plateau;
use App\Models\Site;
use App\Models\SiteAcces;
use App\Models\User;
use App\Services\Temperature\TraitementTemperature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * `GET /api/sites/{uuid}/temperature` (ADR 0011) : état courant de la
 * température de cuisine + cuisson en cours, cloisonné par `site_acces`.
 */
class TemperatureApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Site, 2: Plateau}
     */
    private function foyerAvecPlateauActif(): array
    {
        $user = User::factory()->create();
        $site = Site::factory()->create(['cree_par' => $user->id]);

        SiteAcces::forceCreate([
            'site_id' => $site->id,
            'user_id' => $user->id,
            'niveau' => NiveauAcces::Proprietaire->value,
        ]);

        $plateau = Plateau::factory()->actif()->create(['site_id' => $site->id]);

        return [$user, $site, $plateau];
    }

    public function test_renvoie_l_etat_courant_sans_temperature(): void
    {
        [$user, $site] = $this->foyerAvecPlateauActif();
        Sanctum::actingAs($user);

        $reponse = $this->getJson("/api/sites/{$site->uuid}/temperature");

        $reponse->assertOk()->assertJson([
            'temp_courante_c' => null,
            'frais' => false,
            'cuisson_en_cours' => false,
            'debut_cuisson_at' => null,
        ]);
    }

    public function test_renvoie_la_temperature_courante_et_l_etat_de_cuisson(): void
    {
        [$user, $site, $plateau] = $this->foyerAvecPlateauActif();
        Sanctum::actingAs($user);

        $seuil = (float) config('mesure.seuil_cuisson_c');

        (new TraitementTemperature)->traiter([
            'uid' => $plateau->uid,
            'v' => 1,
            'ts' => now()->timestamp,
            'temp_c' => $seuil + 5,
            'seq' => 1,
        ]);

        $reponse = $this->getJson("/api/sites/{$site->uuid}/temperature");

        $reponse->assertOk()
            ->assertJsonPath('frais', true)
            ->assertJsonPath('cuisson_en_cours', true);

        $this->assertEqualsWithDelta($seuil + 5, $reponse->json('temp_courante_c'), 0.01);
        $this->assertNotNull($reponse->json('debut_cuisson_at'));
    }

    public function test_un_autre_foyer_ne_peut_pas_voir_la_temperature_du_site(): void
    {
        [, $site] = $this->foyerAvecPlateauActif();

        $autreUser = User::factory()->create();
        Sanctum::actingAs($autreUser);

        $this->getJson("/api/sites/{$site->uuid}/temperature")->assertNotFound();
    }
}

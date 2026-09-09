<?php

namespace Tests\Feature;

use App\Contracts\Ia\IaProvider;
use App\Enums\NiveauAcces;
use App\Models\Site;
use App\Models\SiteAcces;
use App\Models\User;
use App\Services\Ia\ClaudeProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * `GET /api/sites/{uuid}/temperature/insights` (ADR 0013, brique 3) : note
 * de sécurité cuisine en langage naturel construite à partir des agrégats
 * `AgregationTemperature` et de l'état courant, cloisonnée au même périmètre
 * que `TemperatureController::analyse`.
 */
class TemperatureInsightsApiTest extends TestCase
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

    public function test_le_provider_simulateur_renvoie_un_apercu_hors_connexion(): void
    {
        [$user, $site] = $this->foyerAvecSite();

        Sanctum::actingAs($user);
        $reponse = $this->getJson("/api/sites/{$site->uuid}/temperature/insights");

        $reponse->assertOk();
        $reponse->assertJsonStructure(['data' => ['insights', 'periode']]);

        $donnees = $reponse->json('data');

        $this->assertIsString($donnees['insights']);
        $this->assertNotSame('', $donnees['insights']);
        $this->assertStringStartsWith('Aperçu hors connexion : ', $donnees['insights']);
        $this->assertSame('mois', $donnees['periode']);
    }

    public function test_le_parametre_periode_semaine_est_accepte(): void
    {
        [$user, $site] = $this->foyerAvecSite();

        Sanctum::actingAs($user);
        $reponse = $this->getJson("/api/sites/{$site->uuid}/temperature/insights?periode=semaine");

        $reponse->assertOk();
        $this->assertSame('semaine', $reponse->json('data.periode'));
        $this->assertNotSame('', $reponse->json('data.insights'));
    }

    public function test_un_autre_foyer_ne_peut_pas_voir_les_insights_du_site(): void
    {
        [, $site] = $this->foyerAvecSite();

        $autreUser = User::factory()->create();
        Sanctum::actingAs($autreUser);

        $this->getJson("/api/sites/{$site->uuid}/temperature/insights")->assertNotFound();
    }

    public function test_le_provider_claude_est_utilise_quand_configure(): void
    {
        // `AppServiceProvider::register()` fige le binding `IaProvider` au
        // démarrage de l'application ; on rebind ici explicitement pour
        // simuler ce que ferait une vraie requête avec `IA_PROVIDER=claude`
        // déjà présent dans l'environnement au boot.
        config(['ia.provider' => 'claude', 'ia.claude.api_key' => 'test-key']);
        $this->app->bind(IaProvider::class, ClaudeProvider::class);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => 'Tout est normal, aucune cuisson en cours.'],
                ],
            ]),
        ]);

        [$user, $site] = $this->foyerAvecSite();

        Sanctum::actingAs($user);
        $reponse = $this->getJson("/api/sites/{$site->uuid}/temperature/insights");

        $reponse->assertOk();
        $this->assertSame('Tout est normal, aucune cuisson en cours.', $reponse->json('data.insights'));

        Http::assertSent(function (ClientRequest $requete) {
            return $requete->url() === 'https://api.anthropic.com/v1/messages'
                && $requete->hasHeader('x-api-key', 'test-key');
        });
    }
}

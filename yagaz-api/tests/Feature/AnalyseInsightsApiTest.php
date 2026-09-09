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
 * `GET /api/analyse/insights` (ADR 0013, brique 1) : insights foyer en
 * langage naturel construits à partir des agrégats `AgregationAnalyse`,
 * cloisonnés au même périmètre que `AnalyseController::index`.
 */
class AnalyseInsightsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_provider_simulateur_renvoie_un_apercu_hors_connexion(): void
    {
        $user = User::factory()->create();
        $site = Site::factory()->create(['cree_par' => $user->id]);

        SiteAcces::forceCreate([
            'site_id' => $site->id,
            'user_id' => $user->id,
            'niveau' => NiveauAcces::Proprietaire->value,
        ]);

        Sanctum::actingAs($user);
        $reponse = $this->getJson('/api/analyse/insights?site_uuid='.$site->uuid);

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
        $user = User::factory()->create();

        Sanctum::actingAs($user);
        $reponse = $this->getJson('/api/analyse/insights?periode=semaine');

        $reponse->assertOk();
        $this->assertSame('semaine', $reponse->json('data.periode'));
        $this->assertNotSame('', $reponse->json('data.insights'));
    }

    public function test_site_uuid_hors_perimetre_renvoie_404(): void
    {
        $proprietaire = User::factory()->create();
        $site = Site::factory()->create(['cree_par' => $proprietaire->id]);
        SiteAcces::forceCreate([
            'site_id' => $site->id,
            'user_id' => $proprietaire->id,
            'niveau' => NiveauAcces::Proprietaire->value,
        ]);

        $autreUser = User::factory()->create();

        Sanctum::actingAs($autreUser);
        $this->getJson('/api/analyse/insights?site_uuid='.$site->uuid)->assertNotFound();
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
                    ['type' => 'text', 'text' => 'Ta consommation est stable ce mois-ci.'],
                ],
            ]),
        ]);

        $user = User::factory()->create();

        Sanctum::actingAs($user);
        $reponse = $this->getJson('/api/analyse/insights');

        $reponse->assertOk();
        $this->assertSame('Ta consommation est stable ce mois-ci.', $reponse->json('data.insights'));

        Http::assertSent(function (ClientRequest $requete) {
            return $requete->url() === 'https://api.anthropic.com/v1/messages'
                && $requete->hasHeader('x-api-key', 'test-key');
        });
    }
}

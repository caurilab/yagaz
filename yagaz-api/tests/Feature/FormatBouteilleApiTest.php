<?php

namespace Tests\Feature;

use App\Models\FormatBouteille;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Référentiel des formats de bouteille (contrat API, §« Formats »).
 */
class FormatBouteilleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_formats_sont_lus_par_un_utilisateur_authentifie(): void
    {
        FormatBouteille::factory()->b6()->create(['marque' => 'Total']);
        FormatBouteille::factory()->b12()->create(['marque' => 'Total']);

        Sanctum::actingAs(User::factory()->create());

        $reponse = $this->getJson('/api/formats');

        $reponse->assertOk();
        $reponse->assertJsonCount(2, 'data');
        $reponse->assertJsonStructure([
            'data' => [['id', 'code', 'marque', 'tare_nominale_g', 'contenance_gaz_g']],
        ]);
    }

    public function test_les_formats_exigent_l_authentification(): void
    {
        $this->getJson('/api/formats')->assertUnauthorized();
    }
}

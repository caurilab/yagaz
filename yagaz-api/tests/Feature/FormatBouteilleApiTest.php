<?php

namespace Tests\Feature;

use App\Models\FormatBouteille;
use App\Models\Marque;
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
            'data' => [['id', 'code', 'marque', 'couleur', 'tare_nominale_g', 'contenance_gaz_g']],
        ]);
    }

    public function test_un_format_expose_la_couleur_de_sa_marque(): void
    {
        $oryx = Marque::factory()->create(['nom' => 'Oryx', 'couleur' => '#1E63B8']);
        FormatBouteille::factory()->b12()->create(['marque' => 'Oryx', 'marque_id' => $oryx->id]);

        Sanctum::actingAs(User::factory()->create());

        $reponse = $this->getJson('/api/formats');

        $reponse->assertOk();
        $reponse->assertJsonPath('data.0.couleur', '#1E63B8');
    }

    public function test_un_format_sans_marque_rattachee_expose_la_couleur_grise_par_defaut(): void
    {
        FormatBouteille::factory()->b12()->create(['marque' => 'Sans Marque', 'marque_id' => null]);

        Sanctum::actingAs(User::factory()->create());

        $reponse = $this->getJson('/api/formats');

        $reponse->assertOk();
        $reponse->assertJsonPath('data.0.couleur', '#6B7280');
    }

    public function test_les_formats_exigent_l_authentification(): void
    {
        $this->getJson('/api/formats')->assertUnauthorized();
    }
}

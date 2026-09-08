<?php

namespace Tests\Feature;

use App\Models\Marque;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Référentiel des marques de gaz (contrat API, §« Marques ») : pour le
 * sélecteur de marque à l'enregistrement d'une bouteille.
 */
class MarqueApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_marques_sont_lues_avec_leur_couleur(): void
    {
        Marque::factory()->create(['nom' => 'Oryx', 'couleur' => '#1E63B8']);
        Marque::factory()->create(['nom' => 'Total', 'couleur' => '#E4032E']);

        Sanctum::actingAs(User::factory()->create());

        $reponse = $this->getJson('/api/marques');

        $reponse->assertOk();
        $reponse->assertJsonCount(2, 'data');
        $reponse->assertJsonStructure(['data' => [['id', 'nom', 'couleur']]]);
        $reponse->assertJsonFragment(['nom' => 'Oryx', 'couleur' => '#1E63B8']);
        $reponse->assertJsonFragment(['nom' => 'Total', 'couleur' => '#E4032E']);
    }

    public function test_les_marques_exigent_l_authentification(): void
    {
        $this->getJson('/api/marques')->assertUnauthorized();
    }
}

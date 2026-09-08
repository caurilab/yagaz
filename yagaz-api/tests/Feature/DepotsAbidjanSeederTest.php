<?php

namespace Tests\Feature;

use App\Models\FormatBouteille;
use App\Models\User;
use Database\Seeders\DepotsAbidjanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * `DepotsAbidjanSeeder` : jeu de données de démonstration réaliste (communes
 * et coordonnées réelles d'Abidjan), pour peupler « dépôts proches ».
 */
class DepotsAbidjanSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_depots_d_abidjan_apparaissent_dans_la_recherche_triee_par_distance(): void
    {
        $this->seed(DepotsAbidjanSeeder::class);

        $formatB12Total = FormatBouteille::where('code', 'B12')->where('marque', 'Total')->firstOrFail();

        Sanctum::actingAs(User::factory()->create());

        // Point de recherche proche du centre de Cocody.
        $reponse = $this->getJson("/api/depots?lat=5.35&lng=-3.99&format_id={$formatB12Total->id}");

        $reponse->assertOk();
        $data = $reponse->json('data');

        $this->assertGreaterThan(1, count($data));

        $distances = array_column($data, 'distance_km');
        $distancesTriees = $distances;
        sort($distancesTriees);
        $this->assertSame($distancesTriees, $distances);

        foreach ($data as $depot) {
            $this->assertTrue($depot['disponible']);
        }
    }
}

<?php

namespace Tests\Feature;

use App\Models\FormatBouteille;
use App\Models\Organisation;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Recherche de dépôts proches (contrat API, §« Recharge ») — lecture seule
 * en Phase 3.
 */
class DepotApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_liste_les_depots_ayant_le_format_en_stock_tries_par_distance(): void
    {
        $format = FormatBouteille::factory()->create();

        $proche = Organisation::factory()->depot()->create(['nom' => 'Dépôt proche', 'lat' => 14.70, 'lng' => -17.45]);
        Stock::create(['organisation_id' => $proche->id, 'format_id' => $format->id, 'pleines' => 10, 'vides' => 2]);

        $loin = Organisation::factory()->depot()->create(['nom' => 'Dépôt loin', 'lat' => 16.00, 'lng' => -16.00]);
        Stock::create(['organisation_id' => $loin->id, 'format_id' => $format->id, 'pleines' => 5, 'vides' => 1]);

        $sansStock = Organisation::factory()->depot()->create(['nom' => 'Dépôt sans stock', 'lat' => 14.71, 'lng' => -17.46]);
        Stock::create(['organisation_id' => $sansStock->id, 'format_id' => $format->id, 'pleines' => 0, 'vides' => 5]);

        Sanctum::actingAs(User::factory()->create());

        $reponse = $this->getJson("/api/depots?lat=14.6928&lng=-17.4467&format_id={$format->id}");

        $reponse->assertOk();
        $reponse->assertJsonCount(2, 'data');
        $reponse->assertJsonPath('data.0.nom', 'Dépôt proche');
        $reponse->assertJsonPath('data.0.disponible', true);
    }

    public function test_depots_exige_les_parametres(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/depots')->assertUnprocessable();
    }
}

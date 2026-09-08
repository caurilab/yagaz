<?php

namespace Database\Seeders;

use App\Models\Marque;
use Illuminate\Database\Seeder;

/**
 * Référentiel des marques de gaz réelles opérant en Côte d'Ivoire, avec des
 * couleurs plausibles (éditables) pour le sélecteur de marque à
 * l'enregistrement d'une bouteille — cf. `FormatBouteilleResource::couleur`.
 *
 * Doit être appelé AVANT `FormatsBouteilleSeeder`, qui rattache chaque
 * format à sa marque (`marque_id`).
 */
class MarquesSeeder extends Seeder
{
    public function run(): void
    {
        // Couleurs réelles observées sur les bouteilles CI (voir _gaz/), éditables.
        $marques = [
            ['nom' => 'Oryx', 'couleur' => '#29A9CE'],          // cyan
            ['nom' => 'Total', 'couleur' => '#E4032E'],          // rouge
            ['nom' => 'Petro Ivoire', 'couleur' => '#26307A'],   // bleu marine
            ['nom' => 'Corlay', 'couleur' => '#2FA84F'],         // vert
            ['nom' => 'Sodigaz', 'couleur' => '#F08A24'],        // orange
            ['nom' => 'GESTOCI', 'couleur' => '#7B4A2E'],        // marron
        ];

        foreach ($marques as $marque) {
            Marque::query()->updateOrCreate(
                ['nom' => $marque['nom']],
                ['couleur' => $marque['couleur']]
            );
        }
    }
}

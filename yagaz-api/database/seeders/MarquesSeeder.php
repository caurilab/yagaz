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
        $marques = [
            ['nom' => 'Oryx', 'couleur' => '#1E63B8'],
            ['nom' => 'Total', 'couleur' => '#E4032E'],
            ['nom' => 'Petro Ivoire', 'couleur' => '#2E9E5B'],
            ['nom' => 'Sodigaz', 'couleur' => '#F08A24'],
            ['nom' => 'GESTOCI', 'couleur' => '#7B4A2E'],
        ];

        foreach ($marques as $marque) {
            Marque::query()->updateOrCreate(
                ['nom' => $marque['nom']],
                ['couleur' => $marque['couleur']]
            );
        }
    }
}

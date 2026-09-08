<?php

namespace Database\Seeders;

use App\Models\FormatBouteille;
use App\Models\Marque;
use Illuminate\Database\Seeder;

/**
 * Référentiel des formats de bouteille courants en Afrique de l'Ouest, pour
 * les deux marques principales (Total, Oryx) — doc 07, §4 `formats_bouteille`.
 *
 * Formats réels observés en Côte d'Ivoire (voir _gaz/), valeurs indicatives
 * (tare à vide / masse de gaz à pleine charge) :
 * - B6  : bouteille 6 kg   — tare ≈ 7 000 g,  gaz ≈ 6 000 g   (trapue)
 * - B12 : bouteille 12,5 kg — tare ≈ 13 000 g, gaz ≈ 12 500 g (haute)
 * - B32 : bouteille 32 kg  — tare ≈ 30 000 g, gaz ≈ 32 000 g  (grande)
 * - B35 : bouteille 35 kg  — tare ≈ 33 000 g, gaz ≈ 35 000 g  (grande)
 * Les tares réelles varient légèrement selon le fabricant ; ce sont les
 * valeurs nominales gravées, affinées ensuite par calibrage (doc 07, §4).
 *
 * Doit être appelé APRÈS `MarquesSeeder` : chaque format est rattaché à sa
 * marque référentielle (`marque_id`), matché sur le nom de la marque (la
 * marque est créée à la volée si absente du référentiel).
 */
class FormatsBouteilleSeeder extends Seeder
{
    public function run(): void
    {
        $formats = [
            ['code' => 'B6', 'tare_nominale_g' => 7000, 'contenance_gaz_g' => 6000],
            ['code' => 'B12', 'tare_nominale_g' => 13000, 'contenance_gaz_g' => 12500],
            ['code' => 'B32', 'tare_nominale_g' => 30000, 'contenance_gaz_g' => 32000],
            ['code' => 'B35', 'tare_nominale_g' => 33000, 'contenance_gaz_g' => 35000],
        ];

        $marques = ['Total', 'Oryx'];

        foreach ($marques as $nomMarque) {
            $marque = Marque::query()->firstOrCreate(
                ['nom' => $nomMarque],
                ['couleur' => '#6B7280']
            );

            foreach ($formats as $format) {
                FormatBouteille::query()->updateOrCreate(
                    ['code' => $format['code'], 'marque' => $nomMarque],
                    [
                        'tare_nominale_g' => $format['tare_nominale_g'],
                        'contenance_gaz_g' => $format['contenance_gaz_g'],
                        'marque_id' => $marque->id,
                    ]
                );
            }
        }

        // Backfill : tout format préexistant non couvert par la boucle
        // ci-dessus (marque hors référentiel connu) reste rattaché à sa
        // marque via le nom de la colonne `marque` legacy.
        FormatBouteille::query()->whereNull('marque_id')->get()->each(function (FormatBouteille $format): void {
            $marque = Marque::query()->firstOrCreate(
                ['nom' => $format->marque],
                ['couleur' => '#6B7280']
            );

            $format->update(['marque_id' => $marque->id]);
        });
    }
}

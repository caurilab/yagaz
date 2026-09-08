<?php

namespace Database\Seeders;

use App\Models\FormatBouteille;
use Illuminate\Database\Seeder;

/**
 * Référentiel des formats de bouteille courants en Afrique de l'Ouest, pour
 * les deux marques principales (Total, Oryx) — doc 07, §4 `formats_bouteille`.
 *
 * Valeurs indicatives réalistes (tare à vide / masse de gaz à pleine charge) :
 * - B6  : bouteille 6 kg  — tare ≈ 7 000 g,  gaz ≈ 6 000 g
 * - B12 : bouteille 12 kg — tare ≈ 13 000 g, gaz ≈ 12 500 g
 * - B24 : bouteille 24 kg — tare ≈ 22 000 g, gaz ≈ 24 000 g
 * Les tares réelles varient légèrement selon le fabricant ; ce sont les
 * valeurs nominales gravées, affinées ensuite par calibrage (doc 07, §4).
 */
class FormatsBouteilleSeeder extends Seeder
{
    public function run(): void
    {
        $formats = [
            ['code' => 'B6', 'tare_nominale_g' => 7000, 'contenance_gaz_g' => 6000],
            ['code' => 'B12', 'tare_nominale_g' => 13000, 'contenance_gaz_g' => 12500],
            ['code' => 'B24', 'tare_nominale_g' => 22000, 'contenance_gaz_g' => 24000],
        ];

        $marques = ['Total', 'Oryx'];

        foreach ($marques as $marque) {
            foreach ($formats as $format) {
                FormatBouteille::query()->updateOrCreate(
                    ['code' => $format['code'], 'marque' => $marque],
                    [
                        'tare_nominale_g' => $format['tare_nominale_g'],
                        'contenance_gaz_g' => $format['contenance_gaz_g'],
                    ]
                );
            }
        }
    }
}

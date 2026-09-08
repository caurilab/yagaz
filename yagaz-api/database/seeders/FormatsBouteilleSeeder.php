<?php

namespace Database\Seeders;

use App\Models\FormatBouteille;
use App\Models\Marque;
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
            ['code' => 'B24', 'tare_nominale_g' => 22000, 'contenance_gaz_g' => 24000],
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

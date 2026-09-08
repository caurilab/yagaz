<?php

namespace Database\Seeders;

use App\Enums\TypeOrganisation;
use App\Models\FormatBouteille;
use App\Models\Organisation;
use App\Models\Stock;
use Illuminate\Database\Seeder;

/**
 * Dépôts réalistes d'Abidjan, pour peupler « dépôts proches » (`GET
 * /api/depots`) avec un jeu de données crédible.
 *
 * IMPORTANT — ce sont des données de DÉMONSTRATION : les noms de dépôt sont
 * fictifs et les communes/coordonnées, bien que géographiquement réelles
 * (centres approximatifs des communes d'Abidjan), ne proviennent d'aucune
 * source live (pas d'appel Google Maps/Places). À ne pas confondre avec des
 * dépôts réels.
 *
 * Rattachés au mandataire de démo (`Mandataire Régional Demo` créé par
 * `DemoSeeder`, réutilisé s'il existe déjà — sinon un mandataire est créé
 * pour que ce seeder reste utilisable isolément).
 */
class DepotsAbidjanSeeder extends Seeder
{
    private const string NOM_MANDATAIRE_DEMO = 'Mandataire Régional Demo';

    /**
     * Communes d'Abidjan avec coordonnées réelles (centre approximatif).
     *
     * @var list<array{commune: string, lat: float, lng: float}>
     */
    private const array COMMUNES = [
        ['commune' => 'Cocody', 'lat' => 5.345, 'lng' => -3.980],
        ['commune' => 'Plateau', 'lat' => 5.324, 'lng' => -4.017],
        ['commune' => 'Yopougon', 'lat' => 5.345, 'lng' => -4.070],
        ['commune' => 'Abobo', 'lat' => 5.418, 'lng' => -4.045],
        ['commune' => 'Marcory', 'lat' => 5.300, 'lng' => -3.990],
        ['commune' => 'Treichville', 'lat' => 5.293, 'lng' => -4.007],
        ['commune' => 'Koumassi', 'lat' => 5.295, 'lng' => -3.960],
    ];

    /**
     * Stock initial par dépôt : quelques formats, pleines > 0 pour apparaître
     * dans `/api/depots`. Quantités variées, indicatives, par commune.
     *
     * @var array<string, array{b12Total: int, b6Total: int, b6Oryx: int}>
     */
    private const array STOCKS = [
        'Cocody' => ['b12Total' => 25, 'b6Total' => 40, 'b6Oryx' => 15],
        'Plateau' => ['b12Total' => 30, 'b6Total' => 20, 'b6Oryx' => 10],
        'Yopougon' => ['b12Total' => 18, 'b6Total' => 35, 'b6Oryx' => 22],
        'Abobo' => ['b12Total' => 12, 'b6Total' => 28, 'b6Oryx' => 8],
        'Marcory' => ['b12Total' => 20, 'b6Total' => 15, 'b6Oryx' => 18],
        'Treichville' => ['b12Total' => 16, 'b6Total' => 24, 'b6Oryx' => 12],
        'Koumassi' => ['b12Total' => 22, 'b6Total' => 30, 'b6Oryx' => 14],
    ];

    public function run(): void
    {
        $mandataire = Organisation::query()
            ->where('type', TypeOrganisation::Mandataire->value)
            ->where('nom', self::NOM_MANDATAIRE_DEMO)
            ->first();

        if (! $mandataire) {
            $mandataire = Organisation::factory()->mandataire()->create([
                'nom' => self::NOM_MANDATAIRE_DEMO,
                'zone' => 'Abidjan',
            ]);
        }

        $formatB12Total = FormatBouteille::query()->firstOrCreate(
            ['code' => 'B12', 'marque' => 'Total'],
            ['tare_nominale_g' => 13000, 'contenance_gaz_g' => 12500]
        );
        $formatB6Total = FormatBouteille::query()->firstOrCreate(
            ['code' => 'B6', 'marque' => 'Total'],
            ['tare_nominale_g' => 7000, 'contenance_gaz_g' => 6000]
        );
        $formatB6Oryx = FormatBouteille::query()->firstOrCreate(
            ['code' => 'B6', 'marque' => 'Oryx'],
            ['tare_nominale_g' => 7000, 'contenance_gaz_g' => 6000]
        );

        foreach (self::COMMUNES as ['commune' => $commune, 'lat' => $lat, 'lng' => $lng]) {
            $depot = Organisation::query()->updateOrCreate(
                ['nom' => "Dépôt Gaz {$commune}"],
                [
                    'type' => TypeOrganisation::Depot,
                    'parent_id' => $mandataire->id,
                    'zone' => $commune,
                    'lat' => $lat,
                    'lng' => $lng,
                    'abonnement_actif' => true,
                ]
            );

            $stocks = self::STOCKS[$commune];

            $this->stock($depot, $formatB12Total, $stocks['b12Total']);
            $this->stock($depot, $formatB6Total, $stocks['b6Total']);
            $this->stock($depot, $formatB6Oryx, $stocks['b6Oryx']);
        }
    }

    private function stock(Organisation $depot, FormatBouteille $format, int $pleines): void
    {
        $stock = Stock::query()
            ->where('organisation_id', $depot->id)
            ->where('format_id', $format->id)
            ->first();

        if ($stock) {
            $stock->forceFill(['pleines' => $pleines])->save();

            return;
        }

        Stock::query()->forceCreate([
            'organisation_id' => $depot->id,
            'format_id' => $format->id,
            'pleines' => $pleines,
            'vides' => max(1, intdiv($pleines, 4)),
            'seuil_plein_bas' => 5,
        ]);
    }
}

<?php

namespace Database\Seeders;

use App\Enums\NiveauAcces;
use App\Enums\RoleBouteille;
use App\Enums\TareSource;
use App\Models\Bouteille;
use App\Models\FormatBouteille;
use App\Models\Organisation;
use App\Models\Plateau;
use App\Models\Site;
use App\Models\SiteAcces;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Jeu de données de démonstration minimal, illustrant la hiérarchie
 * d'organisations et le multi-sites foyer (doc 07, §1 et §3) :
 * un distributeur → un mandataire → deux dépôts, et un foyer avec un site
 * et une bouteille active posée sur un plateau.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $distributeur = Organisation::factory()->distributeur()->create([
            'nom' => 'Distributeur National Demo',
        ]);

        $mandataire = Organisation::factory()->mandataire()->create([
            'nom' => 'Mandataire Régional Demo',
            'parent_id' => $distributeur->id,
        ]);

        $depot1 = Organisation::factory()->depot()->create([
            'nom' => 'Dépôt Demo Centre',
            'parent_id' => $mandataire->id,
        ]);

        $depot2 = Organisation::factory()->depot()->create([
            'nom' => 'Dépôt Demo Périphérie',
            'parent_id' => $mandataire->id,
        ]);

        $foyer = User::factory()->create([
            'name' => 'Foyer Demo',
            'email' => null,
            'telephone' => '+221770000000',
        ]);

        $site = Site::factory()->create([
            'nom' => 'Maison',
            'cree_par' => $foyer->id,
        ]);

        SiteAcces::query()->forceCreate([
            'site_id' => $site->id,
            'user_id' => $foyer->id,
            'niveau' => NiveauAcces::Proprietaire,
        ]);

        $formatB12Total = FormatBouteille::query()->firstOrCreate(
            ['code' => 'B12', 'marque' => 'Total'],
            ['tare_nominale_g' => 13000, 'contenance_gaz_g' => 12500]
        );

        $plateau = Plateau::factory()->actif()->create([
            'site_id' => $site->id,
        ]);

        Bouteille::factory()->create([
            'site_id' => $site->id,
            'format_id' => $formatB12Total->id,
            'plateau_id' => $plateau->id,
            'role_bouteille' => RoleBouteille::Active,
            'tare_g' => $formatB12Total->tare_nominale_g,
            'tare_source' => TareSource::Nominale,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Enums\RoleBouteille;
use App\Enums\TareSource;
use App\Models\Bouteille;
use App\Models\FormatBouteille;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bouteille>
 */
class BouteilleFactory extends Factory
{
    protected $model = Bouteille::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'format_id' => FormatBouteille::factory(),
            'plateau_id' => null,
            'tare_g' => null,
            'tare_source' => TareSource::Nominale,
            'tare_fiable' => false,
            'role_bouteille' => RoleBouteille::Active,
            'seuil_bas_pct' => 15,
        ];
    }

    /**
     * Bouteille de secours (non active) sur le site.
     */
    public function secours(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_bouteille' => RoleBouteille::Secours,
        ]);
    }

    /**
     * Tare calibrée automatiquement et jugée fiable.
     */
    public function calibree(): static
    {
        return $this->state(fn (array $attributes) => [
            'tare_source' => TareSource::Calibree,
            'tare_fiable' => true,
        ]);
    }
}

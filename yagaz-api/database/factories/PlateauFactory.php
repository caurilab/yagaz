<?php

namespace Database\Factories;

use App\Enums\StatutPlateau;
use App\Models\Plateau;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plateau>
 */
class PlateauFactory extends Factory
{
    protected $model = Plateau::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uid' => 'PLT-'.strtoupper(Str::random(6)),
            'secret_hash' => Hash::make(Str::random(20)),
            'site_id' => null,
            'statut' => StatutPlateau::Provisionne,
            'firmware_version' => '1.0.0',
        ];
    }

    /**
     * Plateau installé et actif sur un site.
     */
    public function actif(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => StatutPlateau::Actif,
            'dernier_vu_at' => now(),
        ]);
    }
}

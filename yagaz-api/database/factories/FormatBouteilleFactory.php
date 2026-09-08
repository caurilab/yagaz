<?php

namespace Database\Factories;

use App\Models\FormatBouteille;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormatBouteille>
 */
class FormatBouteilleFactory extends Factory
{
    protected $model = FormatBouteille::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->randomElement(['B6', 'B12', 'B24']),
            'marque' => fake()->randomElement(['Total', 'Oryx']),
            'tare_nominale_g' => 13000,
            'contenance_gaz_g' => 12500,
        ];
    }

    public function b6(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'B6',
            'tare_nominale_g' => 7000,
            'contenance_gaz_g' => 6000,
        ]);
    }

    public function b12(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'B12',
            'tare_nominale_g' => 13000,
            'contenance_gaz_g' => 12500,
        ]);
    }

    public function b24(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'B24',
            'tare_nominale_g' => 24000,
            'contenance_gaz_g' => 24000,
        ]);
    }
}

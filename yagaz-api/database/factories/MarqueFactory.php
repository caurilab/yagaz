<?php

namespace Database\Factories;

use App\Models\Marque;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Marque>
 */
class MarqueFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom' => fake()->unique()->company(),
            'couleur' => fake()->hexColor(),
        ];
    }
}

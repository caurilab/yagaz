<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    protected $model = Site::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom' => fake()->randomElement(['Maison', 'Chez maman', 'Bureau', 'Résidence']),
            'adresse' => fake()->address(),
            'lat' => fake()->latitude(),
            'lng' => fake()->longitude(),
            'cree_par' => User::factory(),
        ];
    }
}

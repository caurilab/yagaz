<?php

namespace Database\Factories;

use App\Enums\StatutTournee;
use App\Models\Organisation;
use App\Models\Tournee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tournee>
 */
class TourneeFactory extends Factory
{
    protected $model = Tournee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory()->mandataire(),
            'livreur_user_id' => null,
            'date' => now()->toDateString(),
            'statut' => StatutTournee::Proposee,
        ];
    }
}

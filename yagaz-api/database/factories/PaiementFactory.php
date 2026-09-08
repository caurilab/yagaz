<?php

namespace Database\Factories;

use App\Enums\StatutPaiement;
use App\Models\Commande;
use App\Models\Paiement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Paiement>
 */
class PaiementFactory extends Factory
{
    protected $model = Paiement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'commande_id' => Commande::factory(),
            'provider' => 'simulateur',
            'reference' => $this->faker->unique()->uuid(),
            'montant' => 1000,
            'devise' => 'XOF',
            'statut' => StatutPaiement::Initie,
        ];
    }
}

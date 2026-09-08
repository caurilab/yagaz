<?php

namespace Database\Factories;

use App\Enums\StatutEquipement;
use App\Enums\TypeEquipement;
use App\Models\Equipement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Equipement>
 */
class EquipementFactory extends Factory
{
    protected $model = Equipement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => TypeEquipement::Balance,
            'reference' => 'EQP-'.strtoupper(Str::random(8)),
            'site_id' => null,
            'statut' => StatutEquipement::AConnecter,
            'cree_par' => User::factory(),
        ];
    }

    /**
     * Équipement affecté à un site et actif (capacité de gating ouverte).
     */
    public function actif(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => StatutEquipement::Actif,
            'dernier_vu_at' => now(),
        ]);
    }

    /**
     * Capteur de température plutôt que balance (par défaut).
     */
    public function temperature(): static
    {
        return $this->state(fn (array $attributes) => ['type' => TypeEquipement::Temperature]);
    }

    /**
     * Écran de cuisine plutôt que balance (par défaut).
     */
    public function ecran(): static
    {
        return $this->state(fn (array $attributes) => ['type' => TypeEquipement::Ecran]);
    }
}

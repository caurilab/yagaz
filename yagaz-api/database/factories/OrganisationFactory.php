<?php

namespace Database\Factories;

use App\Enums\TypeOrganisation;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organisation>
 */
class OrganisationFactory extends Factory
{
    protected $model = Organisation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => TypeOrganisation::Depot,
            'nom' => fake()->company(),
            'telephone' => '+225 07 '.fake()->numerify('## ## ## ##'),
            'parent_id' => null,
            'zone' => fake()->city(),
            'lat' => fake()->latitude(),
            'lng' => fake()->longitude(),
            'abonnement_actif' => true,
        ];
    }

    /**
     * Un dépôt local, éventuellement rattaché à un mandataire.
     */
    public function depot(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TypeOrganisation::Depot,
        ]);
    }

    /**
     * Un mandataire régional.
     */
    public function mandataire(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TypeOrganisation::Mandataire,
        ]);
    }

    /**
     * Un distributeur national.
     */
    public function distributeur(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TypeOrganisation::Distributeur,
        ]);
    }
}

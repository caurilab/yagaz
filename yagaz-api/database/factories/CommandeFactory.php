<?php

namespace Database\Factories;

use App\Enums\ModePaiement;
use App\Enums\OrigineCommande;
use App\Enums\StatutCommande;
use App\Enums\StatutPaiement;
use App\Enums\TypeCommande;
use App\Models\Commande;
use App\Models\FormatBouteille;
use App\Models\Organisation;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Commande>
 */
class CommandeFactory extends Factory
{
    protected $model = Commande::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'origine' => OrigineCommande::Foyer,
            'type' => TypeCommande::Echange,
            'demandeur_user_id' => User::factory(),
            'demandeur_org_id' => null,
            'cible_org_id' => Organisation::factory()->depot(),
            'site_id' => Site::factory(),
            'format_id' => FormatBouteille::factory(),
            'quantite' => 1,
            'statut' => StatutCommande::Proposee,
            'mode_paiement' => ModePaiement::ALaLivraison,
            'statut_paiement' => StatutPaiement::EnAttente,
            'commission_g' => null,
        ];
    }

    /**
     * Commande émise par un dépôt vers son mandataire (réappro).
     */
    public function depuisDepot(): static
    {
        return $this->state(fn (array $attributes) => [
            'origine' => OrigineCommande::Depot,
            'demandeur_user_id' => null,
            'demandeur_org_id' => Organisation::factory()->depot(),
            'cible_org_id' => Organisation::factory()->mandataire(),
            'site_id' => null,
        ]);
    }
}

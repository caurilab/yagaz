<?php

namespace App\Http\Resources;

use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation d'un site (contrat API, §« Sites »). Les attributs
 * `niveau_acces`, `bouteilles_count`, `a_alerte_active` et les capacités
 * `a_balance`/`a_temperature`/`a_ecran` (ADR 0012) sont hydratés par le
 * contrôleur (`SiteController::hydrater()`), pas des colonnes du modèle.
 *
 * @mixin Site
 */
class SiteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'nom' => $this->nom,
            'adresse' => $this->adresse,
            'zone' => $this->zone,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'niveau_acces' => $this->niveau_acces,
            'nb_bouteilles' => $this->bouteilles_count,
            'a_alerte_active' => (bool) $this->a_alerte_active,
            // Capacités dérivées des équipements actifs du site (ADR 0012) :
            // l'app grise le niveau de gaz / la température tant que la
            // capacité correspondante est fausse.
            'a_balance' => (bool) $this->a_balance,
            'a_temperature' => (bool) $this->a_temperature,
            'a_ecran' => (bool) $this->a_ecran,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

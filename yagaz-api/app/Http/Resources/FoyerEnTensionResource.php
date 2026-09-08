<?php

namespace App\Http\Resources;

use App\Models\Bouteille;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une entrée de la file des foyers en tension d'un dépôt (ADR 0009, maillon
 * B, `GET /api/depots/{orgUuid}/foyers-en-tension`). Projection minimale et
 * actionnable (même principe qu'ADR 0008) : ni niveau exact, ni historique,
 * ni contact, ni les autres bouteilles/sites du foyer — seulement de quoi
 * décider de proposer une livraison.
 *
 * `bouteille_en_tension` et `distance_km` sont hydratés par le contrôleur
 * (`DepotCommandeController::foyersEnTension()`), pas des colonnes du modèle.
 *
 * @mixin Site
 */
class FoyerEnTensionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ?Bouteille $bouteille */
        $bouteille = $this->bouteille_en_tension;

        return [
            'site_uuid' => $this->uuid,
            'nom' => $this->nom,
            'zone' => $this->zone,
            'format' => $bouteille?->format !== null ? new FormatBouteilleResource($bouteille->format) : null,
            'distance_km' => $this->distance_km,
        ];
    }
}

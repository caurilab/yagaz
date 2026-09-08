<?php

namespace App\Http\Resources;

use App\Models\Organisation;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vue consolidée d'un dépôt pour son mandataire (contrat API doc 11,
 * `GET /api/mandataires/{orgUuid}/depots`) : stock plein/vide par format,
 * tension, dernière activité.
 *
 * `tension` par format : stock plein au ou sous son seuil bas (rupture
 * proche), ou vides accumulés dépassant ce même seuil (proxy simple, faute
 * d'un seuil dédié aux vides — logistique inversée, doc 11 §1).
 * `derniere_activite` est calculée dans `MandataireController::depots()` et
 * attachée via `setAttribute` (dernier mouvement de stock ou dernière
 * commande ciblant ce dépôt).
 *
 * @mixin Organisation
 */
class DepotConsolideResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $stocks = $this->stocks;

        return [
            'uuid' => $this->uuid,
            'nom' => $this->nom,
            'zone' => $this->zone,
            'stocks' => $stocks->map(fn (Stock $stock) => [
                'format' => new FormatBouteilleResource($stock->format),
                'pleines' => $stock->pleines,
                'vides' => $stock->vides,
                'seuil_plein_bas' => $stock->seuil_plein_bas,
                'tension' => $this->stockEnTension($stock),
            ])->values(),
            'tension' => $stocks->contains(fn (Stock $stock) => $this->stockEnTension($stock)),
            'derniere_activite' => $this->derniere_activite,
        ];
    }

    private function stockEnTension(Stock $stock): bool
    {
        return $stock->pleines <= $stock->seuil_plein_bas || $stock->vides > $stock->seuil_plein_bas;
    }
}

<?php

namespace App\Http\Resources;

use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Stock d'un dépôt pour un format (contrat API doc 10, §4,
 * `GET /api/depots/{orgUuid}/stocks`).
 *
 * @mixin Stock
 */
class StockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'format' => $this->whenLoaded('format', fn () => new FormatBouteilleResource($this->format)),
            'pleines' => $this->pleines,
            'vides' => $this->vides,
            'seuil_plein_bas' => $this->seuil_plein_bas,
            'tension' => $this->pleines <= $this->seuil_plein_bas,
        ];
    }
}

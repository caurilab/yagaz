<?php

namespace App\Http\Resources;

use App\Models\FormatBouteille;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Référentiel des formats de bouteille (contrat API, `GET /api/formats`).
 *
 * @mixin FormatBouteille
 */
class FormatBouteilleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'marque' => $this->marque,
            'tare_nominale_g' => $this->tare_nominale_g,
            'contenance_gaz_g' => $this->contenance_gaz_g,
        ];
    }
}

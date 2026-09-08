<?php

namespace App\Http\Resources;

use App\Models\Organisation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Dépôt proche ayant le format demandé en stock (contrat API,
 * `GET /api/depots`). Lecture seule en Phase 3 : pas de création de
 * commande.
 *
 * @mixin Organisation
 */
class DepotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $stock = $this->stocks->first();

        return [
            'uuid' => $this->uuid,
            'nom' => $this->nom,
            'zone' => $this->zone,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'distance_km' => $this->distance_km,
            'disponible' => $stock !== null && $stock->pleines > 0,
            'stock_pleines' => $stock?->pleines,
        ];
    }
}

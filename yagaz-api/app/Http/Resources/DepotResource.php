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
 * Projection minimale (audit sécurité, [FAIBLE] `GET /depots` expose trop) :
 * ni stock exact (`stock_pleines`, donnée commerciale), ni coordonnées
 * précises du dépôt (`lat`/`lng`) — seule `distance_km` (arrondie) est
 * exposée.
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
            'distance_km' => $this->distance_km !== null ? round($this->distance_km, 1) : null,
            'disponible' => $stock !== null && $stock->pleines > 0,
        ];
    }
}

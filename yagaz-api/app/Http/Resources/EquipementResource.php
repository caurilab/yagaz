<?php

namespace App\Http\Resources;

use App\Models\Equipement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation d'un équipement (contrat API, §« Équipements » -
 * ADR 0012).
 *
 * @mixin Equipement
 */
class EquipementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type->value,
            'reference' => $this->reference,
            'nom' => $this->nom,
            'statut' => $this->statut->value,
            'site' => $this->site !== null ? [
                'uuid' => $this->site->uuid,
                'nom' => $this->site->nom,
            ] : null,
            'dernier_vu_at' => $this->dernier_vu_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

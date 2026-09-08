<?php

namespace App\Http\Resources;

use App\Models\Alerte;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation d'une alerte (contrat API, §« Alertes »). Pas d'`uuid` :
 * l'`id` interne est l'identifiant exposé pour cette ressource (contrat API,
 * `PATCH /api/alertes/{id}`).
 *
 * @mixin Alerte
 */
class AlerteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bouteille_uuid' => $this->bouteille?->uuid,
            'organisation_uuid' => $this->organisation?->uuid,
            'type' => $this->type->value,
            'statut' => $this->statut->value,
            'canal' => $this->canal->value,
            'created_at' => $this->created_at,
        ];
    }
}

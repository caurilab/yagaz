<?php

namespace App\Http\Resources;

use App\Models\Commande;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Demande de réapprovisionnement d'un dépôt vers son mandataire (contrat API
 * doc 11, `GET /api/mandataires/{orgUuid}/reappros`) : une commande
 * `origine = depot` ciblant ce mandataire.
 *
 * @mixin Commande
 */
class ReapproResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'depot' => $this->whenLoaded(
                'demandeurOrg',
                fn () => $this->demandeurOrg !== null ? [
                    'uuid' => $this->demandeurOrg->uuid,
                    'nom' => $this->demandeurOrg->nom,
                    'zone' => $this->demandeurOrg->zone,
                ] : null
            ),
            'format' => $this->whenLoaded('format', fn () => new FormatBouteilleResource($this->format)),
            'quantite' => $this->quantite,
            'statut' => $this->statut->value,
            'created_at' => $this->created_at,
        ];
    }
}

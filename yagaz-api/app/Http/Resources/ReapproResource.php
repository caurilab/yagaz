<?php

namespace App\Http\Resources;

use App\Models\Commande;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Demande de réapprovisionnement d'un dépôt vers son mandataire (ADR 0009,
 * maillon D ; contrat API doc 11, `GET /api/mandataires/{orgUuid}/reappros`
 * et `GET /api/depots/{orgUuid}/reappros`) : une commande `origine = depot`,
 * `demandeur_org` = le dépôt, `cible_org` = le mandataire.
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
            'mandataire' => $this->whenLoaded(
                'cibleOrg',
                fn () => $this->cibleOrg !== null ? [
                    'uuid' => $this->cibleOrg->uuid,
                    'nom' => $this->cibleOrg->nom,
                ] : null
            ),
            'format' => $this->whenLoaded('format', fn () => new FormatBouteilleResource($this->format)),
            'quantite' => $this->quantite,
            'statut' => $this->statut->value,
            'created_at' => $this->created_at,
        ];
    }
}

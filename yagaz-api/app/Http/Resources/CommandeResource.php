<?php

namespace App\Http\Resources;

use App\Models\Commande;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Détail d'une commande, avec la livraison associée si elle existe (contrat
 * API doc 10, §3, `GET /api/commandes` et `GET /api/commandes/{uuid}`).
 *
 * @mixin Commande
 */
class CommandeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'origine' => $this->origine->value,
            'type' => $this->type?->value ?? 'echange',
            'statut' => $this->statut->value,
            'site' => $this->whenLoaded(
                'site',
                fn () => $this->site !== null ? [
                    'uuid' => $this->site->uuid,
                    'nom' => $this->site->nom,
                ] : null
            ),
            'depot' => $this->whenLoaded(
                'cibleOrg',
                fn () => $this->cibleOrg !== null ? [
                    'uuid' => $this->cibleOrg->uuid,
                    'nom' => $this->cibleOrg->nom,
                ] : null
            ),
            'format' => $this->whenLoaded('format', fn () => new FormatBouteilleResource($this->format)),
            'quantite' => $this->quantite,
            'mode_paiement' => $this->mode_paiement->value,
            'statut_paiement' => $this->statut_paiement->value,
            'commission_g' => $this->commission_g,
            'livraison' => $this->whenLoaded(
                'livraison',
                fn () => $this->livraison !== null ? new LivraisonResource($this->livraison) : null
            ),
            'created_at' => $this->created_at,
        ];
    }
}

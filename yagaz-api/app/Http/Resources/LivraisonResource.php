<?php

namespace App\Http\Resources;

use App\Models\Livraison;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Suivi d'une livraison, embarqué dans le détail d'une commande (contrat API
 * doc 10, `GET /api/commandes/{uuid}`) ou renvoyé après un changement de
 * statut. Identifiée par `id` (pas d'`uuid` en base — doc 07, §6
 * `livraisons` — le contrat expose d'ailleurs `PATCH /livraisons/{id}/statut`).
 *
 * @mixin Livraison
 */
class LivraisonResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'statut' => $this->statut->value,
            'livreur' => $this->whenLoaded(
                'livreur',
                fn () => $this->livreur !== null ? new UserPubliqueResource($this->livreur) : null
            ),
            'pleines_deposees' => $this->pleines_deposees,
            'vides_recuperes' => $this->vides_recuperes,
            'affectee_at' => $this->affectee_at,
            'en_route_at' => $this->en_route_at,
            'livree_at' => $this->livree_at,
            'vide_recupere_at' => $this->vide_recupere_at,
        ];
    }
}

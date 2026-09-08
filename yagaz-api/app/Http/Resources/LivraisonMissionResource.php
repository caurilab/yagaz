<?php

namespace App\Http\Resources;

use App\Models\Livraison;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une mission du livreur (contrat API doc 10, §5,
 * `GET /api/livreur/missions`) : adresse (site), bouteille/format, quantité,
 * à déposer / à récupérer, statut.
 *
 * @mixin Livraison
 */
class LivraisonMissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $commande = $this->commande;
        $site = $commande?->site;

        return [
            'id' => $this->id,
            'commande_uuid' => $commande?->uuid,
            'statut' => $this->statut->value,
            'site' => $site !== null ? [
                'uuid' => $site->uuid,
                'nom' => $site->nom,
                'adresse' => $site->adresse,
            ] : null,
            'format' => $commande?->format !== null ? new FormatBouteilleResource($commande->format) : null,
            'quantite' => $commande?->quantite,
            'pleines_a_deposer' => $this->pleines_deposees,
            'vides_a_recuperer' => $commande?->quantite,
            'vides_recuperes' => $this->vides_recuperes,
        ];
    }
}

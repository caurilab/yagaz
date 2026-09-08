<?php

namespace App\Http\Resources;

use App\Models\Tournee;
use App\Models\TourneeLigne;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une tournée de mandataire, avec ses lignes (contrat API doc 11,
 * `GET/POST /api/mandataires/{orgUuid}/tournees`,
 * `PATCH /api/tournees/{uuid}`) — logistique inversée (pleines déposées,
 * vides à récupérer) par dépôt et par format.
 *
 * @mixin Tournee
 */
class TourneeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'date' => $this->date?->toDateString(),
            'statut' => $this->statut->value,
            'livreur' => $this->whenLoaded(
                'livreur',
                fn () => $this->livreur !== null ? new UserPubliqueResource($this->livreur) : null
            ),
            'lignes' => $this->whenLoaded(
                'lignes',
                fn () => $this->lignes->map(fn (TourneeLigne $ligne) => [
                    'depot' => $ligne->depot !== null ? [
                        'uuid' => $ligne->depot->uuid,
                        'nom' => $ligne->depot->nom,
                    ] : null,
                    'format' => $ligne->format !== null ? new FormatBouteilleResource($ligne->format) : null,
                    'pleines' => $ligne->pleines,
                    'vides_a_recuperer' => $ligne->vides_a_recuperer,
                ])->values()
            ),
            'created_at' => $this->created_at,
        ];
    }
}

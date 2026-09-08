<?php

namespace App\Http\Resources;

use App\Models\Bouteille;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une entrée de la file actionnable du livreur habituel (ADR 0008, précision
 * « maillon C » — identité des foyers habituels en tension ;
 * `GET /api/livreur/foyers-en-tension`). Projection dédiée, distincte de
 * `FoyerEnTensionResource` (file du dépôt) : le livreur n'a de droit de regard
 * QUE sur ses propres foyers habituels, et seulement l'identité nécessaire
 * pour proposer une livraison. Jamais le niveau exact, l'autonomie,
 * l'historique, les autres bouteilles/sites du foyer, ni le contact.
 *
 * `bouteille_en_tension` est hydraté par le contrôleur
 * (`LivreurController::foyersEnTension()`), pas une colonne du modèle.
 *
 * @mixin Site
 */
class FoyerEnTensionLivreurResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ?Bouteille $bouteille */
        $bouteille = $this->bouteille_en_tension;

        return [
            'site_uuid' => $this->uuid,
            'nom' => $this->nom,
            'zone' => $this->zone,
            'format' => $bouteille?->format !== null ? new FormatBouteilleResource($bouteille->format) : null,
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\Marque;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Référentiel des marques de gaz (contrat API, `GET /api/marques`) : pour le
 * sélecteur de marque à l'enregistrement d'une bouteille.
 *
 * @mixin Marque
 */
class MarqueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'couleur' => $this->couleur,
        ];
    }
}

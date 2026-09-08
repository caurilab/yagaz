<?php

namespace App\Http\Resources;

use App\Models\Bouteille;
use App\Services\Niveau\NiveauPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation d'une bouteille avec son objet `niveau` embarqué (contrat
 * API, §« Objet niveau »).
 *
 * @mixin Bouteille
 */
class BouteilleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'site_uuid' => $this->site->uuid,
            'format' => new FormatBouteilleResource($this->format),
            'plateau_uid' => $this->plateau?->uid,
            'role_bouteille' => $this->role_bouteille->value,
            'seuil_bas_pct' => $this->seuil_bas_pct,
            'tare_g' => $this->tare_g,
            'tare_source' => $this->tare_source->value,
            'tare_fiable' => $this->tare_fiable,
            'niveau' => app(NiveauPresenter::class)->presenter($this->resource),
        ];
    }
}

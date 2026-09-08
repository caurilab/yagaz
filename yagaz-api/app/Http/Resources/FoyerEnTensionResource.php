<?php

namespace App\Http\Resources;

use App\Models\Bouteille;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une entrée de la file des foyers en tension d'un dépôt (ADR 0009, maillon
 * B, `GET /api/depots/{orgUuid}/foyers-en-tension`). Projection minimale et
 * actionnable (même principe qu'ADR 0008) : ni niveau exact, ni historique,
 * ni contact, ni les autres bouteilles/sites du foyer — seulement de quoi
 * décider de proposer une livraison.
 *
 * `bouteille_en_tension` et `distance_km` sont hydratés par le contrôleur
 * (`DepotCommandeController::foyersEnTension()`), pas des colonnes du modèle.
 *
 * @mixin Site
 */
class FoyerEnTensionResource extends JsonResource
{
    /**
     * Granularité du bucket de distance affiché (audit sécurité, [FAIBLE]
     * bucketiser la distance) : `distance_km` brut (calculé à vol d'oiseau
     * entre le dépôt et le site) situerait le foyer beaucoup plus finement
     * que la `zone` déjà exposée, ce qu'ADR 0008 borne explicitement à la
     * zone. Arrondi au demi-kilomètre SUPÉRIEUR — jamais inférieur, pour ne
     * jamais sous-estimer une distance réelle plus grande.
     */
    private const float GRANULARITE_KM = 0.5;

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
            'distance_km' => $this->bucketiserDistance($this->distance_km),
        ];
    }

    /**
     * Arrondit une distance au bucket de `GRANULARITE_KM` supérieur le plus
     * proche (ex. 2,1 → 2,5 ; 2,5 → 2,5 ; 0,1 → 0,5), `null` inchangé
     * (distance non calculable — dépôt ou site sans coordonnées).
     */
    private function bucketiserDistance(?float $distanceKm): ?float
    {
        if ($distanceKm === null) {
            return null;
        }

        return ceil($distanceKm / self::GRANULARITE_KM) * self::GRANULARITE_KM;
    }
}

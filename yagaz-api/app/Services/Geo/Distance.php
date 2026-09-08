<?php

namespace App\Services\Geo;

/**
 * Distance à vol d'oiseau entre deux points, pour le classement des dépôts
 * proches (contrat API, `GET /api/depots`).
 */
final class Distance
{
    private const float RAYON_TERRE_KM = 6371.0;

    /**
     * Distance en kilomètres (formule de haversine), arrondie à 0,1 km.
     * Retourne `null` si l'une des coordonnées cible est inconnue.
     */
    public function kilometres(float $lat1, float $lng1, ?float $lat2, ?float $lng2): ?float
    {
        if ($lat2 === null || $lng2 === null) {
            return null;
        }

        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);

        $a = sin($deltaLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($deltaLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round(self::RAYON_TERRE_KM * $c, 1);
    }
}

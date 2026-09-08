<?php

namespace App\Services\Mesure;

use App\Models\Bouteille;
use App\Models\Mesure;

/**
 * Estimation du débit de flamme et de l'autonomie (doc 08 §7, ADR 0006).
 *
 * Hypothèses d'implémentation (l'ADR pose la formule et le principe, pas
 * l'algorithme exact d'isolation des pentes) :
 * - On regarde l'historique brut (`poids_g`) de la bouteille sur la fenêtre
 *   `fenetre_debit_jours`, trié par `mesure_at`.
 * - Seules les transitions où le poids **baisse** entre deux mesures
 *   consécutives comptent comme consommation « flamme allumée » ; une
 *   transition où le poids augmente ou reste stable est un rechargement ou
 *   une période inactive et est exclue (ADR 0006).
 * - Le débit observé = somme des baisses / somme des durées associées,
 *   converti en g/h. Il n'est retenu que si l'échantillon est suffisant
 *   (masse et durée cumulées minimales) et reste dans une plage plausible ;
 *   sinon on retombe sur le débit nominal.
 */
final class Autonomie
{
    /**
     * Masse cumulée minimale (g) de décroissance soutenue pour juger le débit
     * observé estimable. Sous ce seuil, l'échantillon est trop bruité/faible.
     */
    private const int MASSE_MINIMALE_ESTIMATION_G = 200;

    /**
     * Durée cumulée minimale (heures) de décroissance soutenue pour juger le
     * débit observé estimable.
     */
    private const float DUREE_MINIMALE_ESTIMATION_H = 1.0;

    /**
     * Plage plausible (g/h) pour un débit observé ; hors de cette plage, on
     * suppose une anomalie de mesure et on retombe sur le nominal.
     */
    private const float DEBIT_MIN_PLAUSIBLE = 10.0;

    private const float DEBIT_MAX_PLAUSIBLE = 2000.0;

    /**
     * Débit de flamme (g/h) à utiliser pour l'autonomie de cette bouteille :
     * observé si estimable sur la fenêtre glissante, sinon le nominal.
     */
    public function estimerDebit(Bouteille $bouteille): float
    {
        $nominal = (float) config('mesure.debit_flamme_nominal');

        $fenetre = now()->subDays((int) config('mesure.fenetre_debit_jours'));

        $mesures = Mesure::query()
            ->where('bouteille_id', $bouteille->id)
            ->where('mesure_at', '>=', $fenetre)
            ->orderBy('mesure_at')
            ->get(['mesure_at', 'poids_g']);

        if ($mesures->count() < 2) {
            return $nominal;
        }

        $masseDecroissanceG = 0;
        $dureeDecroissanceS = 0;

        foreach ($mesures->sliding(2) as $paire) {
            [$precedente, $suivante] = [$paire->first(), $paire->last()];
            $delta = $suivante->poids_g - $precedente->poids_g;

            if ($delta >= 0) {
                // Rechargement (saut positif) ou plateau : hors consommation.
                continue;
            }

            $masseDecroissanceG += -$delta;
            $dureeDecroissanceS += $suivante->mesure_at->diffInSeconds($precedente->mesure_at);
        }

        $dureeDecroissanceH = $dureeDecroissanceS / 3600;

        if ($masseDecroissanceG < self::MASSE_MINIMALE_ESTIMATION_G || $dureeDecroissanceH < self::DUREE_MINIMALE_ESTIMATION_H) {
            return $nominal;
        }

        $debitObserve = $masseDecroissanceG / $dureeDecroissanceH;

        if ($debitObserve < self::DEBIT_MIN_PLAUSIBLE || $debitObserve > self::DEBIT_MAX_PLAUSIBLE) {
            return $nominal;
        }

        return $debitObserve;
    }

    /**
     * Autonomie restante en minutes, arrondie (ADR 0006).
     */
    public function autonomieMinutes(int $gazG, float $debitGParH): int
    {
        if ($debitGParH <= 0) {
            return 0;
        }

        return (int) round($gazG / $debitGParH * 60);
    }
}

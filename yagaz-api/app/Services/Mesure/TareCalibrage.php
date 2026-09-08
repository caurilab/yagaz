<?php

namespace App\Services\Mesure;

use App\Enums\TareSource;
use App\Models\Bouteille;

/**
 * Calibrage automatique de la tare d'une bouteille par le poids plancher
 * observé (doc 08 §5).
 *
 * Hypothèses d'implémentation (le doc pose le principe, pas l'algorithme
 * exact) :
 * - On calibre uniquement sur des poids lissés (`poids_lisse`, cf.
 *   `TraitementMesure::calculerPoidsLisse()`), jamais sur le poids brut, pour
 *   ignorer les à-coups transitoires (§4).
 * - Un poids lissé n'est retenu comme candidat plancher que s'il tombe dans
 *   la plage plausible [nominale − marge, nominale + marge] (garde-fou §5) ;
 *   cela exclut naturellement l'état « sans bouteille » (poids proche de 0).
 * - Tant qu'aucune tare n'a encore été calibrée (`tare_g` null), le premier
 *   candidat plausible devient le plancher initial (c'est la définition même
 *   du « minimum stable observé » : on part de la première observation).
 * - Si un candidat ultérieur est strictement sous le plancher déjà retenu, on
 *   abaisse la tare vers ce nouveau plancher et on réinitialise le compteur
 *   d'observations à 1 (un nouveau plancher, pas encore confirmé). La
 *   comparaison se fait toujours contre le plancher **calibré** (`tare_g`),
 *   jamais contre la tare nominale, pour ne pas fausser le compteur avant
 *   qu'un vrai plancher n'ait été observé.
 * - Si le candidat confirme le plancher courant (à ±`TOLERANCE_CONFIRMATION_G`
 *   près), on incrémente le compteur : c'est l'observation répétée du même
 *   plancher sur des mesures/cycles distincts qui rend la tare fiable.
 * - Après ≥ `n_calibrage` observations (nouvelles ou confirmées), la tare est
 *   marquée fiable (`tare_fiable = true`, `tare_source = calibree`).
 */
final class TareCalibrage
{
    /**
     * Tolérance (en grammes) en deçà de laquelle un poids lissé est considéré
     * comme confirmant le plancher déjà retenu plutôt que d'en indiquer un
     * nouveau. Valeur de départ non listée au doc 08 §10, à ajuster avec des
     * données réelles.
     */
    private const int TOLERANCE_CONFIRMATION_G = 500;

    /**
     * Affine la tare de la bouteille à partir d'un nouveau poids lissé.
     * Modifie et sauvegarde la bouteille si un plancher pertinent est observé.
     */
    public function affiner(Bouteille $bouteille, float $poidsLisse, int $tareNominale): void
    {
        if ($bouteille->tare_fiable) {
            // La tare est déjà jugée fiable : on ne la fait plus bouger, pour
            // éviter qu'un plancher aberrant tardif ne la dégrade.
            return;
        }

        $marge = (int) config('mesure.marge_tare');
        $borneBasse = $tareNominale - $marge;
        $borneHaute = $tareNominale + $marge;

        if ($poidsLisse < $borneBasse || $poidsLisse > $borneHaute) {
            // Hors plage plausible : bruit ou bouteille non posée, on ignore.
            return;
        }

        $tareCalibree = $bouteille->tare_g;
        $plancherCandidat = (int) round($poidsLisse);

        if ($tareCalibree === null || $plancherCandidat < $tareCalibree) {
            $bouteille->tare_g = $plancherCandidat;
            $bouteille->tare_source = TareSource::Calibree;
            $bouteille->calibrage_observations = 1;
        } elseif (abs($plancherCandidat - $tareCalibree) <= self::TOLERANCE_CONFIRMATION_G) {
            $bouteille->calibrage_observations++;
        } else {
            // Poids lissé nettement au-dessus du plancher courant (bouteille
            // pleine ou en cours de consommation) : n'apporte pas d'information
            // sur le plancher, on n'y touche pas.
            return;
        }

        if ($bouteille->calibrage_observations >= (int) config('mesure.n_calibrage')) {
            $bouteille->tare_fiable = true;
        }

        $bouteille->save();
    }
}

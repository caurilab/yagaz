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
 *
 * Réversibilité d'une tare fiable (audit sécurité, correctif #1) : une tare
 * `tare_fiable = true` n'est plus figée à vie. Si un nouveau plancher plus
 * bas, observé à partir d'un poids lissé plausible, contredit nettement (de
 * ≥ `SEUIL_CONTRADICTION_G`) la tare verrouillée, on relance un cycle de
 * calibrage complet sur ce nouveau plancher (`tare_fiable` repasse à false le
 * temps de reconfirmer ce plancher sur `n_calibrage` observations, exactement
 * comme un premier calibrage) — c'est la correction légitime d'un plancher
 * initialement mal calibré. Un candidat au-dessus du plancher verrouillé
 * (bouteille non vide) ou un écart isolé sous ce seuil ne modifie rien :
 * c'est l'hystérésis qui empêche la tare fiable d'osciller à chaque léger
 * bruit de mesure. Ce mécanisme reste borné par la plage plausible
 * [nominale − marge_tare, nominale + marge_tare] vérifiée en amont : une tare
 * ne peut donc jamais dériver, fiable ou non, sous `nominale − marge_tare`.
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
     * Écart (en grammes), en dessous de la tare verrouillée, à partir duquel
     * un nouveau plancher observé est jugé la contredire nettement. En deçà,
     * ou si le candidat est au-dessus du plancher verrouillé (bouteille non
     * vide, sans information sur le plancher), on ne touche à rien —
     * hystérésis anti-oscillation (audit sécurité, correctif #1).
     */
    private const int SEUIL_CONTRADICTION_G = 750;

    /**
     * Affine la tare de la bouteille à partir d'un nouveau poids lissé.
     * Modifie et sauvegarde la bouteille si un plancher pertinent est observé.
     */
    public function affiner(Bouteille $bouteille, float $poidsLisse, int $tareNominale): void
    {
        $marge = (int) config('mesure.marge_tare');
        $borneBasse = $tareNominale - $marge;
        $borneHaute = $tareNominale + $marge;

        if ($poidsLisse < $borneBasse || $poidsLisse > $borneHaute) {
            // Hors plage plausible : bruit ou bouteille non posée, on ignore.
            return;
        }

        $plancherCandidat = (int) round($poidsLisse);

        if ($bouteille->tare_fiable) {
            $this->reconsidererTareFiable($bouteille, $plancherCandidat);

            return;
        }

        $tareCalibree = $bouteille->tare_g;

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

    /**
     * Ré-évalue une tare déjà fiable face à un nouveau candidat plancher (cf.
     * PHPDoc de classe). Ne fait rien si l'écart avec la tare verrouillée
     * reste sous `SEUIL_CONTRADICTION_G` (hystérésis) ; sinon relance un
     * cycle de calibrage complet sur ce nouveau plancher.
     */
    private function reconsidererTareFiable(Bouteille $bouteille, int $plancherCandidat): void
    {
        $tareCalibree = (int) $bouteille->tare_g;

        if ($plancherCandidat >= $tareCalibree - self::SEUIL_CONTRADICTION_G) {
            return;
        }

        $bouteille->tare_g = $plancherCandidat;
        $bouteille->tare_source = TareSource::Calibree;
        $bouteille->tare_fiable = false;
        $bouteille->calibrage_observations = 1;
        $bouteille->save();
    }
}

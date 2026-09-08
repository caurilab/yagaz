<?php

namespace App\Services\Niveau;

use App\Enums\EtatNiveau;
use App\Models\Bouteille;

/**
 * Construit l'objet `niveau` embarqué dans une bouteille (contrat API,
 * §« Objet niveau ») à partir de `niveaux_courants` (cache écrit par
 * `App\Services\Mesure\TraitementMesure`) et des réglages de la bouteille
 * (`seuil_bas_pct`, `tare_fiable`). Centralisé ici pour rester identique
 * partout où une bouteille est exposée (liste, détail).
 */
final class NiveauPresenter
{
    /**
     * Au-delà de ce délai (heures), la dernière mesure connue n'est plus
     * considérée « fraîche » (contrat API, §« Fraîcheur »).
     */
    private const int FRAICHEUR_MAX_HEURES = 6;

    /**
     * Tolérance (g/h) pour juger le débit retenu égal au débit nominal —
     * l'estimation via `App\Services\Mesure\Autonomie::estimerDebit()` peut
     * introduire un écart de flottant négligeable.
     */
    private const float TOLERANCE_DEBIT_NOMINAL = 0.01;

    /**
     * @return array{gaz_g: ?int, niveau_pct: ?int, etat: string, autonomie_min: ?int, autonomie_heures: ?int, debit_g_par_h: ?float, tare_fiable: bool, estimation: bool, calcule_at: ?string, frais: bool}
     */
    public function presenter(Bouteille $bouteille): array
    {
        $niveauCourant = $bouteille->niveauCourant;

        if ($niveauCourant === null) {
            return [
                'gaz_g' => null,
                'niveau_pct' => null,
                'etat' => EtatNiveau::Inconnu->value,
                'autonomie_min' => null,
                'autonomie_heures' => null,
                'debit_g_par_h' => null,
                'tare_fiable' => $bouteille->tare_fiable,
                'estimation' => true,
                'calcule_at' => null,
                'frais' => false,
            ];
        }

        $debitNominal = (float) config('mesure.debit_flamme_nominal');
        $debit = $niveauCourant->debit_g_par_h;
        $estimation = ! $bouteille->tare_fiable
            || ($debit !== null && abs($debit - $debitNominal) < self::TOLERANCE_DEBIT_NOMINAL);

        return [
            'gaz_g' => $niveauCourant->gaz_g,
            'niveau_pct' => $niveauCourant->niveau_pct,
            'etat' => $this->etat($niveauCourant->niveau_pct, $bouteille->seuil_bas_pct)->value,
            'autonomie_min' => $niveauCourant->autonomie_min,
            'autonomie_heures' => $niveauCourant->autonomie_min !== null
                ? (int) round($niveauCourant->autonomie_min / 60)
                : null,
            'debit_g_par_h' => $debit,
            'tare_fiable' => $bouteille->tare_fiable,
            'estimation' => $estimation,
            'calcule_at' => $niveauCourant->calcule_at?->toIso8601String(),
            'frais' => $niveauCourant->calcule_at !== null
                && $niveauCourant->calcule_at->greaterThanOrEqualTo(now()->subHours(self::FRAICHEUR_MAX_HEURES)),
        ];
    }

    /**
     * Traduit un niveau brut en état visuel commun (contrat API,
     * §« États visuels de niveau »).
     */
    private function etat(int $niveauPct, int $seuilBasPct): EtatNiveau
    {
        return match (true) {
            $niveauPct >= 75 => EtatNiveau::Plein,
            $niveauPct >= 40 => EtatNiveau::Correct,
            $niveauPct >= $seuilBasPct => EtatNiveau::Bas,
            default => EtatNiveau::PresqueVide,
        };
    }
}

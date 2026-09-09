<?php

namespace App\Services\Analyse;

use App\Contracts\Ia\IaProvider;
use Illuminate\Support\Collection;

/**
 * Génère les insights foyer en langage naturel à partir des agrégats de
 * l'écran Analyse (ADR 0013, brique 1) : construit un prompt cadré à partir
 * de `AgregationAnalyse::analyser()` et délègue la génération de texte au
 * `IaProvider` lié (`SimulateurIa` par défaut, `ClaudeProvider` si activé).
 *
 * Confidentialité (ADR 0013, §Confidentialité) : seuls les agrégats déjà
 * visibles par l'utilisateur sur son propre écran Analyse sont transmis au
 * provider - jamais d'identifiant (`site_id`/`user_id`/uuid) ni de PII. Le
 * repli en cas d'erreur IA est géré par le contrôleur appelant
 * (`AnalyseController::insights`), pas ici.
 */
final class AssistantFoyer
{
    public function __construct(
        private readonly IaProvider $ia,
        private readonly AgregationAnalyse $agregation
    ) {}

    /**
     * @param  Collection<int, int>  $siteIds
     */
    public function insights(Collection $siteIds, string $periode): string
    {
        $agregats = $this->agregation->analyser($siteIds, $periode);

        return $this->ia->generer($this->instructions(), $this->message($agregats));
    }

    /**
     * Consignes système : rôle, ton et limites de la réponse attendue.
     * Jamais de tiret cadratin long, toujours le tiret simple (`-`),
     * conforme au style d'interface Yagaz.
     */
    private function instructions(): string
    {
        return 'Tu es l\'assistant énergie domestique de Yagaz. Tu aides un '
            .'foyer à comprendre sa consommation de gaz à partir des chiffres '
            .'de son écran Analyse. Réponds en français, tutoie le foyer, '
            .'reste bref et concret, et donne 2 à 3 conseils maximum, '
            .'bienveillants et actionnables. Si besoin de ponctuation, '
            .'utilise uniquement le tiret simple du clavier (-), jamais le '
            .'tiret cadratin long.';
    }

    /**
     * Contenu utilisateur : uniquement les agrégats déjà visibles par le
     * foyer sur son écran Analyse (doc 13, §2), sans aucun identifiant.
     *
     * @param  array<string, mixed>  $agregats
     */
    private function message(array $agregats): string
    {
        $lignes = [
            sprintf(
                'Consommation : %s kg (%s).',
                $agregats['consommation_kg'],
                $this->decrireTendance($agregats['consommation_tendance_pct'])
            ),
            sprintf(
                'Dépense : %s FCFA (%s).',
                $agregats['depense_fcfa'],
                $this->decrireTendance($agregats['depense_tendance_pct'])
            ),
            sprintf('Recharges : %d.', $agregats['recharges']['nombre']),
            sprintf('Jours de cuisine : %d.', $agregats['jours_cuisine']['nombre']),
            sprintf('Autonomie moyenne : %s.', $this->decrireHeures($agregats['autonomie_moyenne_h'])),
        ];

        if ($agregats['projection_prochaine_recharge_jours'] !== null) {
            $lignes[] = sprintf(
                'Prochaine recharge estimée dans %s jours.',
                $agregats['projection_prochaine_recharge_jours']
            );
        }

        return implode("\n", $lignes);
    }

    /**
     * Décrit une variation en pourcentage en langage naturel ; `null` quand
     * `AgregationAnalyse` n'a pas de base de comparaison valable.
     */
    private function decrireTendance(?float $pct): string
    {
        if ($pct === null) {
            return 'tendance non disponible';
        }

        if ($pct > 0.0) {
            return sprintf('en hausse de %s%%', $pct);
        }

        if ($pct < 0.0) {
            return sprintf('en baisse de %s%%', abs($pct));
        }

        return 'stable';
    }

    /**
     * Décrit une autonomie en heures en langage naturel ; `null` quand
     * `AgregationAnalyse` n'a aucune bouteille active avec autonomie connue.
     */
    private function decrireHeures(?float $heures): string
    {
        return $heures === null ? 'non disponible' : sprintf('%s h', $heures);
    }
}

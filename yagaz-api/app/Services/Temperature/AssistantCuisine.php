<?php

namespace App\Services\Temperature;

use App\Contracts\Ia\IaProvider;
use App\Models\SessionCuisson;
use App\Models\Site;
use App\Models\Temperature;

/**
 * Génère la note de sécurité cuisine en langage naturel à partir des
 * agrégats température/cuisson de l'écran Température (ADR 0013, brique 3) :
 * construit un prompt cadré à partir de `AgregationTemperature::analyser()`
 * et de l'état courant (température courante, cuisson en cours), et délègue
 * la génération de texte au `IaProvider` lié (`SimulateurIa` par défaut,
 * `ClaudeProvider` si activé). Calqué sur `App\Services\Analyse\AssistantFoyer`
 * (brique 1).
 *
 * Confidentialité (ADR 0013, §Confidentialité) : seuls les agrégats déjà
 * visibles par l'utilisateur sur son propre écran Température sont transmis
 * au provider - jamais d'identifiant (`site_id`/`user_id`/uuid) ni de PII. Le
 * repli en cas d'erreur IA est géré par le contrôleur appelant
 * (`TemperatureController::insights`), pas ici.
 */
final class AssistantCuisine
{
    public function __construct(
        private readonly IaProvider $ia,
        private readonly AgregationTemperature $agregation
    ) {}

    public function noteSecurite(Site $site, string $periode): string
    {
        $agregats = $this->agregation->analyser($site, $periode);
        $etatCourant = $this->etatCourant($site);

        return $this->ia->generer($this->instructions(), $this->message($agregats, $etatCourant, $periode));
    }

    /**
     * État courant du site : dernière température connue et session de
     * cuisson ouverte - même logique que `TemperatureController::show()`,
     * reprise ici plutôt que dupliquée de façon approximative.
     *
     * @return array{temp_courante_c: ?float, cuisson_en_cours: bool}
     */
    private function etatCourant(Site $site): array
    {
        $derniereTemperature = Temperature::where('site_id', $site->id)
            ->orderByDesc('mesure_at')
            ->orderByDesc('seq')
            ->first();

        $sessionOuverte = SessionCuisson::where('site_id', $site->id)
            ->whereNull('fin_at')
            ->latest('debut_at')
            ->first();

        return [
            'temp_courante_c' => $derniereTemperature?->temp_c,
            'cuisson_en_cours' => $sessionOuverte !== null,
        ];
    }

    /**
     * Libellé en langage naturel de la période analysée, pour éviter que la
     * réponse mentionne une mauvaise période (ex. « semaine » pour un mois).
     */
    private function libellePeriode(string $periode): string
    {
        return match ($periode) {
            'jour' => "aujourd'hui",
            'semaine' => 'cette semaine',
            'annee' => 'cette année',
            default => 'ce mois-ci',
        };
    }

    /**
     * Consignes système : rôle, ton, priorité sécurité et limites de la
     * réponse attendue. Jamais de tiret cadratin long, toujours le tiret
     * simple (`-`), conforme au style d'interface Yagaz.
     */
    private function instructions(): string
    {
        return 'Tu es l\'assistant sécurité cuisine de Yagaz. Tu aides un '
            .'foyer à surveiller sa cuisine à partir des chiffres de son '
            .'écran Température. Ta priorité absolue est la sécurité : gaz '
            .'laissé allumé sans surveillance, surchauffe prolongée. Réponds '
            .'en français, tutoie le foyer, reste bref et bienveillant, et '
            .'donne 1 à 3 conseils maximum, actionnables. Si tout est normal, '
            .'rassure le foyer en une phrase plutôt que d\'inventer un risque. '
            .'Si besoin de ponctuation, utilise uniquement le tiret simple du '
            .'clavier (-), jamais le tiret cadratin long. La période analysée '
            .'est précisée en tête des données : parle de cette période-là '
            .'(ex. « ce mois-ci ») et d\'aucune autre. Ne pose jamais de '
            .'diagnostic médical et n\'affole jamais inutilement le foyer.';
    }

    /**
     * Contenu utilisateur : uniquement les agrégats déjà visibles par le
     * foyer sur son écran Température, plus l'état courant, sans aucun
     * identifiant.
     *
     * @param  array<string, mixed>  $agregats
     * @param  array{temp_courante_c: ?float, cuisson_en_cours: bool}  $etatCourant
     */
    private function message(array $agregats, array $etatCourant, string $periode): string
    {
        $lignes = [
            sprintf('Période analysée : %s.', $this->libellePeriode($periode)),
            sprintf('Température courante : %s.', $this->decrireTemperature($etatCourant['temp_courante_c'])),
            sprintf('Cuisson en cours : %s.', $etatCourant['cuisson_en_cours'] ? 'oui' : 'non'),
            sprintf('Pic de température : %s.', $this->decrirePic($agregats['pic_temperature'])),
            sprintf('Heure de pointe de cuisson : %s.', $this->decrireHeure($agregats['heure_pointe_cuisson'])),
            sprintf('Période dominante de cuisine : %s.', $agregats['periode_dominante'] ?? 'non disponible'),
            sprintf(
                'Fréquence de cuisine : %s session(s)/jour, durée moyenne %s min.',
                $agregats['frequence']['sessions_par_jour'],
                $agregats['frequence']['duree_moyenne_min']
            ),
        ];

        return implode("\n", $lignes);
    }

    /**
     * Décrit la température courante en langage naturel ; `null` quand
     * aucun relevé n'existe encore pour ce site.
     */
    private function decrireTemperature(?float $tempC): string
    {
        return $tempC === null ? 'non disponible' : sprintf('%s °C', $tempC);
    }

    /**
     * Décrit le pic de température de la période en langage naturel ; `null`
     * quand `AgregationTemperature` n'a aucun relevé sur la période.
     *
     * @param  array{heure: ?int, temp_c: ?float}  $picTemperature
     */
    private function decrirePic(array $picTemperature): string
    {
        if ($picTemperature['temp_c'] === null || $picTemperature['heure'] === null) {
            return 'non disponible';
        }

        return sprintf('%s °C à %sh', $picTemperature['temp_c'], $picTemperature['heure']);
    }

    /**
     * Décrit une heure de la journée (0..23) en langage naturel ; `null`
     * quand `AgregationTemperature` n'a aucune cuisson sur la période.
     */
    private function decrireHeure(?int $heure): string
    {
        return $heure === null ? 'non disponible' : sprintf('%sh', $heure);
    }
}

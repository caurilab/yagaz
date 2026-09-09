<?php

namespace App\Services\Ia;

use App\Contracts\Ia\IaProvider;
use Illuminate\Support\Str;

/**
 * Implémentation par défaut de `IaProvider` (ADR 0013, brique 1, analogue à
 * `SimulateurPaiement`/`CanalLog`) : répond de façon déterministe, sans
 * jamais appeler un modèle réel, ce qui permet de faire tourner l'app, les
 * tests et la CI sans clé. Un modèle réel (`App\Services\Ia\ClaudeProvider`)
 * la remplacera, liée dans `AppServiceProvider`, sans toucher aux services
 * appelants.
 *
 * La réponse varie légèrement selon des mots-clés déjà présents dans
 * `$message` (construit par le service appelant à partir d'agrégats déjà en
 * français, ex. « en hausse »/« en baisse ») pour rester crédible sans
 * introduire la moindre logique métier ni le moindre appel réseau ici.
 */
final class SimulateurIa implements IaProvider
{
    /**
     * Préfixe systématique de toute réponse simulée : signale sans ambiguïté
     * au foyer (et aux tests) qu'il ne s'agit pas d'une vraie réponse IA.
     */
    private const string PREFIXE = 'Aperçu hors connexion : ';

    public function generer(string $instructions, string $message): string
    {
        $conseils = [$this->observationPrincipale($message)];

        $conseils[] = $this->conseilEconomie($message);
        $conseils[] = $this->conseilAutonomie($message);

        return self::PREFIXE.implode(' ', array_filter($conseils));
    }

    /**
     * Première phrase, qui reflète la tendance de consommation/dépense
     * détectée dans `$message`, ou un message neutre si aucune tendance
     * n'y figure (foyer sans historique suffisant).
     */
    private function observationPrincipale(string $message): string
    {
        if (Str::contains($message, 'hausse')) {
            return 'Ta consommation ou ta dépense est en hausse sur la période : surveille les jours de forte cuisine et vérifie l\'étanchéité des raccords.';
        }

        if (Str::contains($message, 'baisse')) {
            return 'Bonne nouvelle, ta consommation ou ta dépense est en baisse sur la période : continue sur cette lancée.';
        }

        return 'Voici un aperçu de ta consommation de gaz sur la période.';
    }

    /**
     * Conseil d'économie générique, adapté si des recharges récentes
     * apparaissent dans `$message`.
     */
    private function conseilEconomie(string $message): string
    {
        if (Str::contains($message, 'Recharges : 0')) {
            return 'Pas de recharge récente : garde un œil sur l\'autonomie restante pour anticiper la prochaine commande.';
        }

        return 'Pense à comparer le coût de tes dernières recharges pour repérer le meilleur moment pour commander.';
    }

    /**
     * Conseil sur l'autonomie/la prochaine recharge, uniquement si
     * `$message` contient une projection exploitable.
     */
    private function conseilAutonomie(string $message): string
    {
        if (Str::contains($message, 'Prochaine recharge estimée')) {
            return 'Anticipe ta prochaine commande avant que le niveau ne passe sous le seuil bas.';
        }

        return 'Installe un plateau connecté sur toutes tes bouteilles actives pour affiner ces conseils.';
    }
}

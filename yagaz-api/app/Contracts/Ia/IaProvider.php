<?php

namespace App\Contracts\Ia;

/**
 * Contrat d'un fournisseur de génération de texte par IA (ADR 0013, brique 1
 * — insights foyer). L'implémentation par défaut
 * (`App\Services\Ia\SimulateurIa`) répond sans appel réseau ; un modèle réel
 * (`App\Services\Ia\ClaudeProvider`, API Anthropic Messages) se substituera
 * à elle — liée dans `AppServiceProvider` — sans toucher aux services
 * appelants (ex. `App\Services\Analyse\AssistantFoyer`).
 */
interface IaProvider
{
    /**
     * Génère un texte à partir de consignes système (`$instructions`, rôle
     * et style attendus) et d'un contenu utilisateur (`$message`, les
     * données concrètes sur lesquelles répondre).
     */
    public function generer(string $instructions, string $message): string;
}

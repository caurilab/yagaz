<?php

// Configuration de l'intégration IA (ADR 0013, brique 1, insights foyer).
// L'abstraction `App\Contracts\Ia\IaProvider` est liée à l'implémentation
// choisie ici dans `AppServiceProvider::register()` ; brancher Claude se
// limite à poser `IA_PROVIDER=claude` et `IA_CLAUDE_API_KEY`, sans toucher
// aux contrôleurs ni aux services métier.
return [

    // Sélecteur de haut niveau (prioritaire sur `provider` ci-dessous) :
    // `laravel_ai` route toute l'IA par le package `laravel/ai` (SDK unifié,
    // provider/modèle lus dans le bloc `laravel_ai`). Vide/null : on retombe
    // sur `provider` (simulateur|claude), le comportement historique. Non
    // défini en test/CI -> simulateur, aucun appel réseau.
    'driver' => env('IA_DRIVER'),

    // Réglages du driver `laravel/ai` (package `laravel/ai`). Le provider
    // (`anthropic`) et sa clé (`ANTHROPIC_API_KEY`) sont définis dans le
    // `config/ai.php` du package ; ici on choisit le provider et le modèle à
    // utiliser pour la génération de texte de l'app.
    'laravel_ai' => [
        'provider' => env('AI_PROVIDER', 'anthropic'),
        'model' => env('AI_MODEL', 'claude-opus-5'),
    ],

    // Implémentation liée par `AppServiceProvider` : `simulateur` (défaut,
    // aucun appel réseau réel) tant qu'aucune clé Claude n'est disponible ;
    // l'application, les tests et la CI tournent sans clé.
    'provider' => env('IA_PROVIDER', 'simulateur'),

    // Identifiants et réglages de l'API Anthropic Messages, consommés par
    // `App\Services\Ia\ClaudeProvider` ; tous vides/par défaut tant que la
    // clé n'est pas disponible : `ClaudeProvider` refuse alors tout appel
    // réseau (exception explicite) plutôt que d'échouer silencieusement.
    'claude' => [
        'api_key' => env('IA_CLAUDE_API_KEY', ''),
        'model' => env('IA_CLAUDE_MODEL', 'claude-sonnet-5'),
        'base_url' => env('IA_CLAUDE_BASE_URL', 'https://api.anthropic.com'),
        'version' => env('IA_CLAUDE_VERSION', '2023-06-01'),
        'max_tokens' => (int) env('IA_CLAUDE_MAX_TOKENS', 400),
        'timeout' => (int) env('IA_CLAUDE_TIMEOUT', 20),
    ],

];

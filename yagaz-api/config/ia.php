<?php

// Configuration de l'intégration IA (ADR 0013, brique 1, insights foyer).
// L'abstraction `App\Contracts\Ia\IaProvider` est liée à l'implémentation
// choisie ici dans `AppServiceProvider::register()` ; brancher Claude se
// limite à poser `IA_PROVIDER=claude` et `IA_CLAUDE_API_KEY`, sans toucher
// aux contrôleurs ni aux services métier.
return [

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

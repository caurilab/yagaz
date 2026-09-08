<?php

// Configuration du paiement Mobile Money (ADR 0010, v2 brique 1). L'abstraction
// `App\Contracts\Payment\PaymentProvider` est liée à l'implémentation choisie
// ici dans `AppServiceProvider::register()` — brancher un agrégateur réel se
// limite à ajouter une classe et changer `PAIEMENT_PROVIDER`, sans toucher au
// cycle de commande ni aux contrôleurs.
return [

    // Implémentation liée par `AppServiceProvider` : `simulateur` (défaut,
    // aucun appel réseau réel) tant que l'agrégateur n'est pas tranché.
    'provider' => env('PAIEMENT_PROVIDER', 'simulateur'),

    // Secret partagé utilisé pour calculer/vérifier la signature des
    // webhooks entrants (ADR 0010, §Sécurité) — jamais commité, toujours en
    // variable d'environnement.
    'secret_webhook' => env('PAIEMENT_SECRET_WEBHOOK'),

    // Devise par défaut des paiements (Afrique de l'Ouest, ADR 0010).
    'devise' => env('PAIEMENT_DEVISE', 'XOF'),

    // Identifiants de l'agrégateur Mobile Money (type CinetPay/Semoa/
    // PayDunya) consommés par `App\Services\Payment\AgregateurPaiement` —
    // tous vides par défaut tant que le compte agrégateur n'est pas
    // disponible : `AgregateurPaiement` refuse alors tout appel réseau
    // (exception explicite) plutôt que d'échouer silencieusement.
    'agregateur' => [
        'base_url' => env('PAIEMENT_AGREGATEUR_BASE_URL', ''),
        'api_key' => env('PAIEMENT_AGREGATEUR_API_KEY', ''),
        'site_id' => env('PAIEMENT_AGREGATEUR_SITE_ID', ''),
        'secret' => env('PAIEMENT_AGREGATEUR_SECRET', ''),
        'return_url' => env('PAIEMENT_AGREGATEUR_RETURN_URL', ''),
    ],

];

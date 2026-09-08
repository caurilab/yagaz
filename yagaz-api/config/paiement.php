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

];

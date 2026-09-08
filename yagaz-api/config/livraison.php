<?php

// Constantes du suivi de commande (contrat API, `GET /api/commandes/{uuid}/suivi`).
return [

    // Vitesse urbaine moyenne (km/h) utilisée pour estimer le temps restant
    // avant livraison à partir de la distance à vol d'oiseau dépôt↔site
    // (App\Services\Geo\Distance, haversine sur `lat`/`lng`). C'est une
    // ESTIMATION grossière — pas de position GPS live du livreur — cohérente
    // avec une circulation urbaine ouest-africaine typique (embouteillages,
    // arrêts) ; App\Services\Commande\SuiviCommande.
    'vitesse_urbaine_kmh' => (float) env('LIVRAISON_VITESSE_URBAINE_KMH', 18),

];

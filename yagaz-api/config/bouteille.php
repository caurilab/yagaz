<?php

// Référentiel des pièces amovibles d'une bouteille (tare ajustable - pièces
// manquantes). `delta_g` sont des VALEURS PAR DÉFAUT AJUSTABLES : le
// mécanisme (tare de référence du format moins le poids des pièces
// manquantes cochées) prime sur les grammages exacts, modifiables ici sans
// toucher au code. Exposé en lecture via `GET /api/pieces-bouteille`
// (contrat API, §« Bouteilles ») et consommé par
// `BouteilleController::tareAjustee()`.
return [
    'pieces_amovibles' => [
        ['cle' => 'collerette', 'libelle' => 'Collerette / arceau de protection', 'delta_g' => 250],
        ['cle' => 'poignee', 'libelle' => 'Poignée', 'delta_g' => 150],
        ['cle' => 'chapeau', 'libelle' => 'Chapeau de valve', 'delta_g' => 80],
    ],
];

<?php

// Constantes de la chaîne de mesure (doc 08, §10). Toutes les valeurs sont
// en grammes / grammes-par-heure / jours sauf mention contraire, et lisibles
// via config('mesure.xxx').
return [

    // Poids brut au-delà duquel une mesure est physiquement aberrante et
    // rejetée (doc 08 §3).
    'poids_max_absolu' => (int) env('MESURE_POIDS_MAX_ABSOLU', 60000),

    // En dessous de ce poids, on considère qu'aucune bouteille n'est posée
    // sur le plateau (doc 08 §3).
    'seuil_plateau_nu' => (int) env('MESURE_SEUIL_PLATEAU_NU', 1000),

    // Nombre d'observations de plancher nécessaires avant de marquer la tare
    // d'une bouteille comme fiable (doc 08 §5).
    'n_calibrage' => (int) env('MESURE_N_CALIBRAGE', 5),

    // Plage plausible autour de la tare nominale du format, dans laquelle un
    // plancher observé est retenu pour le calibrage (doc 08 §5).
    'marge_tare' => (int) env('MESURE_MARGE_TARE', 2000),

    // Débit de flamme nominal utilisé tant que le débit observé n'est pas
    // estimable (ADR 0006).
    'debit_flamme_nominal' => (int) env('MESURE_DEBIT_FLAMME_NOMINAL', 150),

    // Fenêtre glissante (en jours) sur laquelle on tente d'estimer le débit
    // observé à partir des pentes de décroissance soutenues (ADR 0006).
    'fenetre_debit_jours' => (int) env('MESURE_FENETRE_DEBIT_JOURS', 14),

];

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
    //
    // Resserrée de 2000 à 1000 g (audit sécurité, correctif #1) : à 2000 g,
    // la tare pouvait être calibrée jusqu'à `nominale − 2000`, ce qui dépasse
    // la masse de gaz correspondant au seuil d'alerte des petits formats
    // (ex. B6 : 6000 g × 15 % = 900 g). Une tare sous-estimée dans cette
    // marge pouvait donc afficher un niveau au-dessus du seuil alors que la
    // bouteille est physiquement vide, sans jamais déclencher d'alerte. À
    // 1000 g, ce risque persiste en théorie pour les formats les plus petits
    // mais reste couvert, indépendamment de la marge de tare, par le filet
    // de sécurité absolu `securite_marge_plancher_g` ci-dessous.
    'marge_tare' => (int) env('MESURE_MARGE_TARE', 1000),

    // Filet de sécurité absolu, indépendant de la tare calibrée (audit
    // sécurité, correctif #1) : une bouteille active dont le poids brut
    // lissé descend à `format.tare_nominale_g + cette marge` déclenche une
    // alerte seuil_bas, même si la tare calibrée est faussée ou pas encore
    // fiable — une bouteille physiquement quasi vide reste détectée.
    'securite_marge_plancher_g' => (int) env('MESURE_SECURITE_MARGE_PLANCHER_G', 500),

    // Fenêtre récente (en heures) sur laquelle la déduplication par couple
    // (plateau_id, seq) est effectuée (audit sécurité, correctif #2) :
    // au-delà, un `seq` déjà vu est accepté comme nouvelle mesure — c'est le
    // cas d'un plateau qui redémarre et repart avec un `seq` bas. Le filet
    // dur contre l'insertion en double exacte reste la clé primaire
    // composite (plateau_id, mesure_at, seq) de la table `mesures`.
    'dedup_fenetre_heures' => (int) env('MESURE_DEDUP_FENETRE_HEURES', 6),

    // Bornes de plausibilité d'un message entrant (audit sécurité, correctif
    // #4) : au-delà, le message est rejeté. Distinct du bornage de dérive
    // d'horloge de `resoudreMesureAt()`, qui recale les petites dérives sans
    // rejeter le message.
    'seq_max' => (int) env('MESURE_SEQ_MAX', 9_000_000_000_000),
    'ts_min' => env('MESURE_TS_MIN', '2020-01-01'),

    // Débit de flamme nominal utilisé tant que le débit observé n'est pas
    // estimable (ADR 0006).
    'debit_flamme_nominal' => (int) env('MESURE_DEBIT_FLAMME_NOMINAL', 150),

    // Fenêtre glissante (en jours) sur laquelle on tente d'estimer le débit
    // observé à partir des pentes de décroissance soutenues (ADR 0006).
    'fenetre_debit_jours' => (int) env('MESURE_FENETRE_DEBIT_JOURS', 14),

    // Capteur de température de cuisine (ADR 0011). Au-dessus de ce seuil,
    // une cuisson est considérée en cours (session ouverte/maintenue).
    'seuil_cuisson_c' => (float) env('MESURE_SEUIL_CUISSON_C', 40),

    // Au-dessus de ce seuil, la température est jugée dangereuse : alerte
    // sécurité `temperature_elevee` adressée au foyer (ADR 0011).
    'seuil_danger_c' => (float) env('MESURE_SEUIL_DANGER_C', 60),

    // Anti-rebond (température soutenue) avant d'ouvrir/fermer une session de
    // cuisson (ADR 0011). Non utilisé en v1 (voir la note d'implémentation de
    // `TraitementTemperature::traiterCuisson()`), conservé pour calibrage
    // futur du matériel réel.
    'duree_min_cuisson_s' => (int) env('MESURE_DUREE_MIN_CUISSON_S', 120),

];

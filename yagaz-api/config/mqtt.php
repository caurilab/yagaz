<?php

// Configuration de la connexion au broker MQTT utilisé pour l'ingestion
// des mesures des bouteilles de gaz (poids, pression, etc.).
return [

    // Adresse et port du broker MQTT.
    'host' => env('MQTT_HOST', '127.0.0.1'),
    'port' => (int) env('MQTT_PORT', 1883),

    // Identifiants du client d'ingestion (worker) sur le broker.
    'username' => env('MQTT_USERNAME'),
    'password' => env('MQTT_PASSWORD'),

    // Identifiant du client MQTT utilisé par le worker d'ingestion Yagaz.
    'client_id' => 'yagaz-api-ingest',

];

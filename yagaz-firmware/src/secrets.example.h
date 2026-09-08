// Copier ce fichier en src/secrets.h (ignoré par git) et renseigner les vraies
// valeurs. Ne jamais commiter src/secrets.h.
#pragma once

// --- Wi-Fi ---
#define WIFI_SSID     "mon-wifi"
#define WIFI_PASSWORD "mot-de-passe-wifi"

// --- MQTT ---
#define MQTT_HOST     "192.168.1.10"   // IP du broker sur le réseau local
#define MQTT_PORT     1883
#define MQTT_USER     "PLT-XXXXXX"      // = identifiant du plateau (uid)
#define MQTT_PASSWORD "secret-du-plateau"

// --- Identité du plateau ---
#define PLATEAU_UID   "PLT-XXXXXX"      // immuable, gravé au provisioning

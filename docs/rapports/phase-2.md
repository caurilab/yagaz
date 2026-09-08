# Rapport de phase — Phase 2 : Ingestion et chaîne de mesure

Date : 2026-09-08

## Objectif

Recevoir les mesures des plateaux par MQTT, les traiter de façon robuste
(aberrations, doublons, trous, réseau instable), convertir le poids en niveau et
en autonomie, gérer la tare (saisie + calibrage automatique), et fournir un
firmware ESP32 de test. Sécuriser l'authentification des appareils.

## Construit

### Chaîne d'ingestion (`yagaz-api`)
- **Service `TraitementMesure`** (découplé du transport, donc testable) :
  résolution/authentification du plateau, validation, déduplication, résolution
  de la bouteille, rangement de la mesure (hypertable), lissage (EMA), calibrage
  de la tare, calcul du niveau et de l'autonomie, déclenchement des alertes.
- **Sous-services** : `TareCalibrage` (plancher observé, bornes de plausibilité,
  re-correction), `Autonomie` (débit observé vs nominal, ADR 0006).
- **Commande `yagaz:ingest`** : consommateur MQTT (`php-mqtt/client`), abonné à
  `yagaz/v1/plateau/+/mesure`, `uid` via le topic, reconnexion automatique,
  isolation par message (un message toxique ne bloque pas les autres).
- Constantes de réglage dans `config/mesure.php`.

### Firmware plateau (`yagaz-firmware`)
- Lecture HX711 moyennée + lissage léger, calibrage (tare plateau + facteur
  d'échelle) persisté en NVS, procédure série `TARE` / `CAL:<g>`.
- Wi-Fi + MQTT (LWT retained, publication au format ADR 0003, `seq` monotone),
  horodatage NTP, échantillonnage adaptatif (rapide si consommation active).

### Sécurité MQTT
- **ACL Mosquitto par appareil** : un plateau ne publie que sur son propre topic ;
  seul le compte `yagaz-ingest` écoute l'ensemble. Un plateau compromis reste
  cloisonné à sa propre bouteille.

## Sécurité (audit Opus + corrections)
Audit de l'ingestion mené en Opus. Corrections appliquées :
- **Critique — panne sèche silencieuse** : une tare sous-estimée (dans la marge
  autorisée) pouvait masquer l'alerte de niveau bas. Corrigé par un **filet de
  sécurité indépendant de la tare** (alerte dès que le poids brut approche la
  tare nominale) + resserrement de `marge_tare` + tare non figée à vie.
- **Élevé — reset de `seq`** au redémarrage d'un plateau, qui gelait l'ingestion :
  déduplication sur fenêtre récente (6 h) au lieu du `max(seq)` global ; filet
  dur via la clé primaire composite.
- **Bornage** des entrées (`seq`, `ts`), **assainissement des logs** (jamais le
  payload brut complet).
- **Transport** : `message_size_limit` broker ; TLS préparé et rendu obligatoire
  en production (ADR 0007), ingestion mono-worker (sérialisation requise avant
  scale-out).

## Tests
- **35 tests, 86 assertions, tout vert** (dont les phases précédentes).
- Ingestion : uid inconnu/non-actif rejeté, doublon ignoré, aberration rejetée,
  niveau qui baisse, calibrage de la tare qui converge, alerte de seuil unique,
  secours sans alerte ; **tare faussée n'empêche pas l'alerte de sécurité**,
  reset de `seq` accepté, bornes `seq`/`ts`, l'`uid` du message fait foi.
- CI verte (migrations sur vrai TimescaleDB + suite complète).

## Décisions (ADR)
- 0006 — Modèle d'autonomie (débit flamme nominal 150 g/h, raffiné par observation).
- 0007 — Sécurité de l'ingestion (TLS en prod, mono-worker).

## Écarts au cadrage signalés
- `bouteilles.calibrage_observations` ajouté (compteur de calibrage, non prévu au
  doc 07).
- Valeurs de départ à affiner avec les données réelles du prototype : tolérance
  de confirmation du plancher (500 g), seuil de re-correction de tare (750 g),
  bornes de plausibilité du débit (10–2000 g/h), fenêtre de dédup (6 h).

## Limites / dette
- Firmware non compilé/flashé ici (pas de toolchain PlatformIO) : `pio run` et le
  calibrage réel restent à faire à l'atelier. `PubSubClient` publie en QoS 0 côté
  client ; la déduplication `seq` absorbe ce compromis (à réévaluer au flash).
- TLS MQTT à activer avant toute mise en production hors réseau confiné.
- Le raffinement fin du débit « flamme allumée » (détection de pentes) est posé
  structurellement mais mérite d'être itéré avec de vraies données.
- Agrégats continus TimescaleDB (vue distributeur) : prévus en Phase 5.

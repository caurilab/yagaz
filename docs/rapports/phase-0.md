# Rapport de phase — Phase 0 : Fondations

Date : 2026-09-08

## Objectif

Poser les fondations du monorepo : dépôt Git, quatre sous-projets, infra locale,
gestion des secrets, base d'authentification, intégration continue.

## Construit

### Socle monorepo
- Dépôt Git initialisé (`main`), historique lisible, un commit par intention.
- Structure alignée sur le cadrage : le dossier vide `yagaz-desktop` a été
  remplacé par **`yagaz-web`** (nom utilisé partout dans les docs). Décision
  signalée : voir « Écarts au cadrage » plus bas.
- `.gitignore` couvrant secrets, dépendances et données locales.

### Infrastructure locale (Docker Compose)
- `db` : TimescaleDB (Postgres 17) — métier + séries temporelles.
- `mqtt` : Mosquitto 2 — ingestion capteur (TCP 1883 + WS 9001), **sans accès
  anonyme**, compte de service authentifié.
- `redis` : files d'attente Laravel.
- Contrat d'environnement racine `.env.example`, init TimescaleDB, config
  Mosquitto, `ops/README.md`. Prérequis : Docker Desktop (ADR 0002).

### yagaz-api (Laravel 13.30.1 / PHP 8.5)
- Connexion pgsql + Redis (queue/cache/session).
- Auth API via **Sanctum** (`HasApiTokens` sur `User`) — base du modèle d'auth.
- `php-mqtt/client` installé pour le futur worker d'ingestion ; `config/mqtt.php`.
- `laravel/boost` en dev.
- Endpoint `GET /api/health` (sans auth). Tests par défaut : 2/2 verts.

### yagaz-web (React 19.2 / Vite 8)
- React Router, TanStack Query, axios (intercepteur Bearer).
- Palette corail/blanc en variables CSS, `AppShell` (sidebar), `KpiCard`.
- Page Vue d'ensemble mandataire (KPI + dépôts en tension, données placeholder).
- Build de production vert, vérifié visuellement.

### yagaz-app (Expo SDK 57 / React Native 0.86 / React 19)
- Expo Router, TypeScript, thème corail/blanc.
- Écran foyer de démonstration : autonomie en heures en héro, badge d'état,
  niveau %, autres bouteilles (placeholders). `tsc --noEmit` vert.

### yagaz-firmware (ESP32 / PlatformIO)
- Squelette de projet : `platformio.ini` (HX711, PubSubClient, ArduinoJson),
  `main.cpp` structuré avec TODO, `secrets.example.h` (secrets hors dépôt).

### Intégration continue
- GitHub Actions : tests API (services TimescaleDB + Redis), build web,
  typecheck app. Déclenché sur push/PR.

## Sécurité (au fil de l'eau)
- Secrets hors dépôt : `.env`, `ops/mosquitto/passwd`, `src/secrets.h` ignorés.
  Scan des fichiers trackés : aucun secret réel commité.
- Mosquitto sans accès anonyme dès la fondation.
- Auth API posée (Sanctum) — le cloisonnement métier vient en Phase 1.

## Tests
- API : 2/2 (par défaut, sqlite en mémoire).
- Web : build de production sans erreur TS.
- App : `tsc --noEmit` sans erreur.
- Les tests dépendant de la base (migrations Postgres/Timescale) démarreront en
  Phase 1, une fois l'infra Docker lancée.

## Décisions (ADR)
- 0002 — Infra locale via Docker Compose.
- 0003 — Format des messages MQTT (topic versionné, JSON compact, QoS 1).
- 0004 — Paiement : commande sans paiement en ligne en v1, point d'extension isolé.

## Écarts au cadrage signalés
- **`yagaz-desktop` → `yagaz-web`** : le disque contenait un dossier vide
  `yagaz-desktop` ; toutes les docs disent `yagaz-web`. Aligné sur les docs
  (confirmé par le choix « React 19 comme les docs »).
- **Stack** : conflit entre le CLAUDE.md global (Nuxt/Bootstrap) et le cadrage
  (React). Tranché avec le porteur : React 19 (web) + React Native (mobile).

## Limites / à faire à l'ouverture de la Phase 1
- Docker n'était pas installé pendant cette session : l'infra est **configurée**
  mais n'a pas été **démarrée** ni les migrations exécutées. À faire dès que
  Docker Desktop est disponible (`make infra-up`).
- Laravel Boost a généré `yagaz-api/CLAUDE.md` et `AGENTS.md` (guidelines par
  défaut), laissés tels quels.

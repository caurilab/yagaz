# ADR 0002 — Infrastructure locale via Docker Compose

Date : 2026-09-08
Statut : accepté

## Contexte

Le développement local a besoin de trois briques que le cadrage impose :
PostgreSQL avec l'extension TimescaleDB (séries temporelles des mesures), un
broker MQTT (ingestion capteur), et des files d'attente (Redis pour Laravel).

La machine de développement dispose de PHP, Node, Bun, git et d'un PostgreSQL
Homebrew, mais **ni Docker, ni Mosquitto, ni TimescaleDB** n'étaient installés.
Deux options : installer chaque service en natif via Homebrew, ou tout regrouper
dans Docker Compose.

## Décision

**Docker Compose** à la racine du monorepo (`docker-compose.yml`), avec trois
services : `db` (image `timescale/timescaledb:2.17.2-pg17`), `mqtt`
(`eclipse-mosquitto:2`) et `redis`. Choix confirmé par le porteur du projet.

## Alternatives écartées

- **Installation Homebrew native** : plus légère à démarrer mais dépendante de
  la machine, difficile à reproduire à l'identique entre développeurs et en CI,
  et mélange TimescaleDB avec le PostgreSQL Homebrew existant.

## Conséquences

- Prérequis : Docker Desktop installé sur le poste de développement.
- Un seul `cp .env.example .env` + `docker compose up -d` monte tout l'environnement.
- Les données vivent dans `ops/data/` (ignoré par git).
- La configuration Mosquitto et l'init TimescaleDB sont versionnées dans `ops/`.
- La même image TimescaleDB servira de base à la configuration de production
  (à cadrer par l'agent devops le moment venu).

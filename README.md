# Yagaz

Plateforme de suivi de bouteilles de gaz butane pour l'Afrique de l'Ouest.

Un plateau connecté placé sous chaque bouteille mesure son poids, en déduit le
niveau de gaz restant et l'autonomie en heures de flamme. Cette donnée remonte
toute la chaîne logistique : du foyer au dépôt de quartier, du dépôt au
mandataire, du mandataire au distributeur.

## Structure du dépôt

Ce dépôt est un **monorepo** : un seul dépôt Git à la racine, plusieurs projets
dans des dossiers séparés.

```
yagaz/
├── .git/                  Un seul dépôt, à la racine
├── .claude/
│   └── agents/            Fiches des sous-agents (une par rôle)
├── .ai/
│   └── guidelines/        Conventions de travail communes à tous les agents
├── docs/
│   ├── decisions/         Décisions d'architecture (ADR), au fil de l'eau
│   ├── etat-du-projet.md  Photo de l'avancement, mise à jour en continu
│   └── ...                Cadrage produit, spécifications
├── yagaz-api/             Backend Laravel 13 — https://api.yagaz.test
├── yagaz-web/             Dashboards web React 19 — https://web.yagaz.test
├── yagaz-app/             Application mobile React Native (Expo)
└── yagaz-firmware/        Firmware ESP32 du plateau (à venir)
```

## Les trois projets applicatifs

| Dossier | Rôle | Stack | URL locale |
|---|---|---|---|
| `yagaz-api` | API, logique métier, ingestion des mesures | Laravel 13 + PostgreSQL/TimescaleDB + MQTT | https://api.yagaz.test |
| `yagaz-web` | Tableaux de bord mandataire et distributeur | React 19 | https://web.yagaz.test |
| `yagaz-app` | App foyer, dépôt, mandataire, livreur | React Native (Expo) | — |

## Versioning

Un seul `.git`, à la racine. Les sous-projets ne sont **pas** des dépôts
séparés : ce sont des dossiers du même dépôt.

Règle de branches : **une branche par tâche**, préfixée par le domaine touché.

```
api/feat-ingestion-mesures
app/feat-ecran-niveau
web/fix-auth-token
firmware/feat-lecture-hx711
docs/adr-choix-mqtt
```

Branches de courte durée, mergées vite. Voir `.ai/guidelines/git.md` pour la
convention complète et la façon dont on évite les collisions entre agents.

## Par où commencer

1. Lire `docs/` pour le cadrage produit (vision, modèle économique, PRD,
   architecture, matériel, UX/UI).
2. Lire `.ai/guidelines/` avant d'écrire la moindre ligne de code.
3. Consulter `.claude/agents/README.md` pour savoir quel agent fait quoi.

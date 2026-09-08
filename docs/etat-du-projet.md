# État du projet — Yagaz

Photo de l'avancement, mise à jour en continu par l'archiviste.

Dernière mise à jour : 2026-09-08 (Phase 1 terminée)

## En un coup d'œil

Le projet est en **développement**. **Phases 0 et 1 terminées.** Phase 0 :
monorepo Git, quatre sous-projets scaffoldés, infra Docker, CI, auth de base,
secrets hors dépôt. Phase 1 : schéma de données complet (21 migrations, 17
modèles, hypertable TimescaleDB), multi-sites, cloisonnement des acteurs
(policies + tests), audit de sécurité mené et corrigé. **CI verte** (migrations
sur vrai TimescaleDB, tests, build web, typecheck app). Le dépôt est sur GitHub
(`caurilab/yagaz`). Prochaine étape : Phase 2 (ingestion MQTT + chaîne de mesure
+ firmware). Le prototype matériel reste en cours de sourcing.

## Fait

- Cadrage produit complet : vision, modèle économique, PRD, architecture,
  matériel et sourcing, UX/UI (voir les documents de `docs/`).
- Stack technique arrêtée (voir `.ai/guidelines/stack.md`).
- Choix du monorepo et structure du dépôt (voir ADR 0001).
- Structure des agents et guidelines de collaboration.
- Sourcing des composants du prototype identifié (kit HX711 + cellules 50 kg,
  ESP32 DevKit).
- **Phase 0 — Fondations** (voir `docs/rapports/phase-0.md`) : dépôt Git,
  scaffolding des 4 sous-projets, infra Docker (TimescaleDB + MQTT + Redis),
  CI, auth Sanctum, secrets hors dépôt. ADR 0002/0003/0004.
- **Modèle de données v1** conçu (`docs/07-modele-de-donnees.md`).
- **Phase 1 — Modèle de données et socle métier** (voir `docs/rapports/phase-1.md`) :
  21 migrations, 17 modèles, hypertable TimescaleDB, multi-sites, cloisonnement
  (12 policies + tests), audit sécurité corrigé. ADR 0005. CI verte.

## En cours

- **Phase 2 — Ingestion et chaîne de mesure** : worker MQTT, traitement robuste
  (aberrations, doublons, trous), conversion poids → niveau → autonomie, gestion
  de la tare, firmware ESP32 de test.
- Commande des composants du prototype (3 plateaux de test).

## À venir

- Phase 2 : ingestion MQTT + chaîne de mesure + firmware ESP32 réel.
- Phase 3 : app foyer complète. Phase 4 : dépôt + boucle de commande.
- Phase 5 : mandataire + distributeur. Phase 6 : consolidation.
- Démarrer l'infra Docker et exécuter les migrations (Docker Desktop requis).

## À trancher

- **Paiement en ligne / Mobile Money** : non encore discuté, laissé en backlog
  dans le PRD. Sujet à ouvrir.
- Format précis des messages MQTT publiés par le plateau.
- Modèle de données détaillé (un document dédié est proposé).

## Bloqué

- Rien pour l'instant.

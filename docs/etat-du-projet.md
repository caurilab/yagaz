# État du projet — Yagaz

Photo de l'avancement, mise à jour en continu par l'archiviste.

Dernière mise à jour : 2026-09-08 (Phase 0 terminée)

## En un coup d'œil

Le projet est passé du cadrage au **développement**. **Phase 0 (Fondations)
terminée** : monorepo Git, quatre sous-projets scaffoldés (API Laravel, web
React 19, app Expo, firmware ESP32), infra locale Docker (TimescaleDB + MQTT +
Redis), CI, auth de base et gestion des secrets. Le modèle de données v1 est
conçu (`docs/07-modele-de-donnees.md`). Prochaine étape : Phase 1 (migrations et
socle métier). Le prototype matériel reste en cours de sourcing.

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

## En cours

- **Phase 1 — Modèle de données et socle métier** : migrations à partir de
  `docs/07-modele-de-donnees.md`, hypertable TimescaleDB, contraintes de
  cloisonnement.
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

# État du projet — Yagaz

Photo de l'avancement, mise à jour en continu par l'archiviste.

Dernière mise à jour : 2026-09-08 (Phase 3 terminée)

## En un coup d'œil

Le projet est en **développement**. **Phases 0 à 3 terminées.** Fondations, socle
de données + cloisonnement, chaîne d'ingestion MQTT + firmware, et **application
foyer** (contrat d'API + endpoints backend + écrans React Native, hors ligne).
Chaque phase a fait l'objet d'un audit de sécurité Opus mené et corrigé. **CI
verte** : 81 tests / 259 assertions, migrations sur vrai TimescaleDB, build web,
typecheck app. Dépôt sur GitHub (`caurilab/yagaz`). Prochaine étape : Phase 4
(dépôt + boucle de commande). Le prototype matériel reste en cours de sourcing.

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
- **Phase 2 — Ingestion et chaîne de mesure** (voir `docs/rapports/phase-2.md`) :
  service d'ingestion MQTT, calibrage tare, niveau/autonomie, alertes, firmware
  ESP32, ACL par appareil, audit sécurité corrigé. ADR 0006/0007. CI verte.
- **Phase 3 — App foyer** (voir `docs/rapports/phase-3.md`) : contrat d'API v1,
  endpoints backend (auth/sites/bouteilles/formats/alertes/dépôts), app React
  Native (accueil autonomie, multi-sites, enregistrement, hors ligne), audit
  sécurité corrigé (rate-limiting, normalisation téléphone). CI verte.

## En cours

- **Phase 4 — Dépôt et boucle de commande** : app dépôt (stock plein/vide,
  commandes entrantes), boucle foyer → dépôt → livraison, rôle livreur.
- Commande des composants du prototype (3 plateaux de test).

## À venir

- Phase 5 : mandataire + distributeur (dashboards web). Phase 6 : consolidation.
- Démarrer l'infra Docker (ou créer la base `yagaz` locale) et exécuter les
  migrations. Activer TLS MQTT avant prod (ADR 0007). Flasher/calibrer le firmware.

## À trancher

- **Paiement en ligne / Mobile Money** : non encore discuté, laissé en backlog
  dans le PRD. Sujet à ouvrir.
- Format précis des messages MQTT publiés par le plateau.
- Modèle de données détaillé (un document dédié est proposé).

## Bloqué

- Rien pour l'instant.

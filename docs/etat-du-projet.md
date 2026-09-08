# État du projet — Yagaz

Photo de l'avancement, mise à jour en continu par l'archiviste.

Dernière mise à jour : 2026-09-08 (concept central fermé)

## En un coup d'œil

**Les six phases v1 sont construites, testées et sécurisées, ET le concept
central est fermé.** La chaîne se déclenche désormais toute seule : une bouteille
active sous le seuil → notification foyer + livreur habituel → file de tension
côté dépôt et livreur → proposition (l'humain confirme) → et, côté stock dépôt,
réappro préparé automatiquement vers le mandataire. Chaque transmission vers un
acteur tiers est bornée au minimum et vérifiée par un test d'étanchéité. Un audit
de sécurité Opus par incrément, corrigé au fil de l'eau. **CI verte** : 168 tests
backend, migrations sur vrai TimescaleDB, build web, typecheck app. Dépôt sur
GitHub (`caurilab/yagaz`).

**v2 — brique 1 (Paiement Mobile Money) livrée** derrière l'abstraction
(simulateur + webhook signé + réconciliation ; app foyer câblée ; audit Opus
corrigé). 185 tests backend verts. Briques v2 restantes (écran de cuisine,
recettes, donnée agrégée) **en attente de leur préalable** (retour terrain,
prototype éprouvé, densité du parc) — voir `docs/rapports/rapport-v2.md`.

Voir aussi `docs/rapports/rapport-final.md` (v1) et
`docs/rapports/rapport-cloture-concept-central.md` (déclenchement automatique).
Le prototype matériel reste en sourcing.

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
- **Phase 4 — Dépôt et boucle de commande** (voir `docs/rapports/phase-4.md`) :
  cycle commande/livraison transactionnel, stock, espaces dépôt et livreur,
  suivi foyer, audit sécurité corrigé. CI verte.
- **Phase 5 — Mandataire et distributeur** (voir `docs/rapports/phase-5.md`) :
  vue consolidée + tournées mandataire (web + mobile), agrégats régionaux
  distributeur (étanches au foyer), notifications, audit sécurité corrigé. CI verte.
- **Phase 6 — Consolidation** (voir `docs/rapports/phase-6.md` et
  `rapport-final.md`) : non-régression, revue produit globale, rapport final +
  recommandations.

## À venir (recommandations — voir rapport final §6-7)

- **Compléter le trigger de la chaîne** : notifier le livreur habituel / le dépôt
  au seuil bas, boucle réappro dépôt → mandataire, file des foyers en tension.
- **Compléments d'expérience** : multilingue (i18n), carte des dépôts, tare par photo.
- **Mise en prod** : infra réelle + migrations, TLS MQTT (ADR 0007), provisioning
  des plateaux, vrais canaux de notification (push/SMS/WhatsApp).
- **Matériel** : compiler/flasher/calibrer le firmware, trancher secteur/batterie.

## À trancher

- **Paiement en ligne / Mobile Money** : non encore discuté, laissé en backlog
  dans le PRD. Sujet à ouvrir.
- Format précis des messages MQTT publiés par le plateau.
- Modèle de données détaillé (un document dédié est proposé).

## Bloqué

- Rien pour l'instant.

# Rapport de phase — Phase 1 : Modèle de données et socle métier

Date : 2026-09-08

## Objectif

Concevoir et implémenter le schéma de données (acteurs, sites, plateaux,
bouteilles, mesures, commandes, stocks, tournées), porter le multi-sites,
utiliser une hypertable TimescaleDB pour les mesures, et matérialiser le
cloisonnement entre acteurs par des contraintes de données autant que par du code.

## Construit

### Modèle de données (`docs/07-modele-de-donnees.md`)
- **21 migrations** couvrant tout le schéma : `users` étendu (uuid, téléphone,
  langue), `organisations` (hiérarchie dépôt → mandataire → distributeur),
  `memberships`, `sites` + `site_acces` (multi-sites), `formats_bouteille`,
  `plateaux`, `bouteilles`, `mesures`, `niveaux_courants`, `stocks`,
  `mouvements_stock`, `commandes`, `tournees`, `livraisons`, `alertes`,
  `livreur_habituel`.
- **17 modèles Eloquent** reliés, **17 enums PHP**, trait `HasUuid`.
- **Factories** + seeders (formats de référence B6/B12/B24, jeu de démo
  hiérarchique complet).

### Multi-sites et cloisonnement
- L'accès foyer à un site passe exclusivement par `site_acces` (propriétaire,
  gestionnaire, observateur) — le cas « surveiller la bouteille d'un proche »
  est un simple accès observateur.
- Les acteurs pro sont bornés par `organisation_id`, avec accès descendant dans
  la hiérarchie (un mandataire voit ses dépôts, un distributeur sa branche).
- **Helpers d'autorisation** sur `User` + **12 policies** (Site, Bouteille,
  Plateau, Organisation, Stock, Commande, Mesure, NiveauCourant, Livraison,
  Tournee, MouvementStock, Alerte).

### TimescaleDB
- Table `mesures` en **hypertable** (PK composite `(plateau_id, mesure_at, seq)`
  incluant la colonne de partition, comme l'exige Timescale). Création de
  l'hypertable conditionnelle à la présence de l'extension : un PostgreSQL local
  sans Timescale garde une table classique (dev), Docker/CI crée l'hypertable.

## Sécurité (au fil de l'eau + audit dédié)
Audit de sécurité mené en Opus. Corrections appliquées dans la foulée :
- **Mass-assignment** : `Membership` et `SiteAcces` (les tables d'autorisation)
  ne sont plus mass-assignables (ADR 0005) ; toute tentative lève une exception.
- `Plateau.secret_hash` masqué en sérialisation.
- `CommandePolicy::update` restreinte à la gestion directe (plus la chaîne).
- Contrainte anti auto-référence sur `organisations.parent_id`.
- `sites.cree_par` en `restrictOnDelete` (ne détruit plus un site partagé).
- Contrainte « alerte non orpheline ».
- Policies ajoutées sur les entités sensibles : un acteur pro ne peut **jamais**
  voir une `Mesure` ou un `NiveauCourant` de foyer (garantie doc 04 §8 / doc 07 §9).

## Tests
- **17 tests, 47 assertions, tout vert.**
- `CloisonnementTest` : isolation foyer↔foyer, observateur en lecture seule,
  bouteille/plateau suivent le site, plateau non posé invisible, stock
  dépôt↔dépôt, mandataire voit sans saisir, branche distributeur, membership
  inactif, livreur non-gestionnaire, visibilité commande, unicité bouteille active.
- `SecuriteCloisonnementTest` : pro ne voit pas mesure/niveau de foyer,
  mass-assignment bloqué, portée de `update` sur commande.
- **CI verte** : migrations exécutées sur un vrai TimescaleDB (hypertable), tests
  sur SQLite en mémoire, build web, typecheck app.

## Décisions (ADR)
- 0005 — Posture d'écriture et mass-assignment (FormRequest + colonnes
  d'autorisation dérivées côté serveur pour les Phases 3+).

## Écarts au cadrage signalés
- `mesures` : PK composite `(plateau_id, mesure_at, seq)` au lieu de la paire
  `(plateau_id, mesure_at)` du doc 07 — imposé par TimescaleDB (tout index unique
  doit inclure la colonne de partition) ; `seq` assure l'idempotence.
- Géolocalisation : `lat`/`lng` (decimal) au lieu d'un type `point`, pour la
  portabilité SQLite (tests) / PostgreSQL.

## Limites / dette
- Les 15 autres modèles restent en `#[Guarded([])]` : ils seront verrouillés par
  `$fillable` explicite + FormRequest au moment où leurs endpoints d'écriture
  sont créés (Phase 3), conformément à l'ADR 0005.
- CI : `actions/checkout@v4` déclenche un avertissement Node 20 (sans impact) —
  à bumper à l'occasion.
- Tests exécutés sur SQLite ; le schéma PostgreSQL/Timescale est validé par le
  step Migrations. Des tests d'intégration PostgreSQL pourront être ajoutés plus
  tard sur une base pg sans Timescale (pour éviter le conflit de drop d'hypertable).

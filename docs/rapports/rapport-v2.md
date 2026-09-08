# Rapport v2 — avancement de la feuille de route

Date : 2026-09-08
Dépôt : `github.com/caurilab/yagaz` (branche `main`, CI verte)

Suite à la fermeture du concept central (`rapport-cloture-concept-central.md`),
enchaînement sur la feuille de route v2 (`docs/12-perspectives-v2.md`), dans
l'ordre des préalables. Une brique dont le préalable n'est pas levé n'est pas
forcée : elle est documentée et mise en attente.

## Brique 1 — Paiement Mobile Money : LIVRÉE (derrière l'abstraction)

Préalable (décision sur les moyens de paiement locaux) **non tranché** →
valeur par défaut prise et documentée (ADR 0010), intégration construite
**derrière l'abstraction** déjà isolée en v1 (ADR 0004).

**Construit :**
- Abstraction `PaymentProvider` (initier / vérifier notification / extraire
  résultat / statut) + implémentation par défaut `SimulateurPaiement` (HMAC, sans
  réseau). Provider choisi par `config/paiement.php` — brancher un agrégateur réel
  = fournir une implémentation, sans toucher au reste.
- Machine à états du paiement (`en_attente → initie → regle | echoue | expire`),
  `mode_paiement = mobile_money`, table `paiements` (ledger) avec unique
  `(provider, reference)` pour l'idempotence.
- Flux : le foyer initie le paiement d'une commande confirmée ; l'opérateur
  notifie via **webhook signé** ; réconciliation pour les paiements en attente.
- App foyer : bouton « Payer par Mobile Money », suivi de statut (polling),
  rappel que le paiement à la livraison reste possible.

**Sécurité (audit Opus, corrigé) — verdict : cœur sûr.**
- Pas de forge de webhook sans le secret (signature vérifiée en temps constant),
  pas de faux `regle`, statut de paiement jamais cru du client, cloisonnement
  strict (un foyer ne paie que ses commandes), montant/devise fixés et vérifiés
  côté serveur, idempotence sous concurrence (verrou + unique DB).
- Corrigé : garde anti **double-facturation** (refus de ré-initier une commande
  déjà réglée, réutilisation de l'intention en cours), borne de taille du webhook.

**Tests :** 185 backend, 645 assertions, verts. CI verte.

**À trancher / brancher (préalables produit et prod) :**
- Décision « agrégateur vs intégration directe » et choix de l'agrégateur.
- Implémentation de l'opérateur/agrégateur réel (identifiants, webhook signé sur
  **corps brut**, limiteur dédié au webhook — notés dans le code).
- **Grille tarifaire** : un prix unitaire placeholder (XOF) est utilisé (comme la
  commission en v1) ; une vraie grille de prix par format/marque est à définir.

## Brique 2 — Écran de cuisine comme produit : EN ATTENTE

Préalable = **retour terrain sur l'usage réel de l'écran** (qu'attend la personne
qui cuisine sans l'app ?). Non disponible dans cette session (pas de déploiement
terrain). Non forcée. Le hardware « écran » est prévu au sourcing ; son
intégration produit attend ce retour.

## Brique 3 — Couche recettes : EN ATTENTE

Préalable = **mesure d'autonomie éprouvée**, donc après le prototype physique
(calibrage sur vraies bouteilles). Non disponible (firmware non flashé/calibré,
pas de données réelles). Non forcée. Les primitives AI de Laravel, déjà dans la
stack, serviront ici le moment venu.

## Brique 4 — Donnée agrégée : EN ATTENTE

Préalable = **densité du parc** (assez de plateaux actifs pour des agrégats
significatifs). Non atteint (pas de parc déployé). Non forcée. **Rappel de
sécurité** : à l'ouverture de cette brique, le **k-anonymat** des agrégats
(recommandation #8 du rapport final) cesse d'être optionnel — c'est un préalable
dur avant toute exposition de la donnée. Les fondations (TimescaleDB, agrégats
régionaux étanches au foyer) sont déjà posées.

## En résumé

| Brique | État | Débloquée par |
|---|---|---|
| 1 Paiement Mobile Money | **Livrée** (derrière l'abstraction) | décision paiement (défaut pris, ADR 0010) |
| 2 Écran de cuisine | En attente | retour terrain sur l'usage |
| 3 Couche recettes | En attente | mesure d'autonomie éprouvée (prototype) |
| 4 Donnée agrégée | En attente | densité du parc (+ k-anonymat, préalable dur) |

Seule la brique 1 avait son préalable « débloquable » dans cette session (via une
valeur par défaut derrière l'abstraction). Les briques 2 à 4 dépendent de faits
du monde réel (terrain, prototype, parc) absents ici ; elles sont prêtes à être
reprises dès que leur préalable est levé, chacune avec son propre cadrage et, si
elle engage l'architecture, son ADR.

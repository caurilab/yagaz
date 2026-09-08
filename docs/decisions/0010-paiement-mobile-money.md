# ADR 0010 — Paiement Mobile Money (v2, brique 1)

Date : 2026-09-08
Statut : accepté (valeur par défaut ; l'intégration d'un opérateur réel reste à brancher)

## Contexte

La v1 a laissé le paiement « à la livraison » avec un point d'extension isolé
(ADR 0004 : champs `mode_paiement`/`statut_paiement`, cycle de commande découplé
du paiement). La feuille de route v2 (`docs/12-perspectives-v2.md`) place le
paiement Mobile Money en premier. Son préalable — la décision sur les moyens de
paiement locaux (opérateurs, agrégateur ou intégration directe) — n'est **pas
tranché**. Conformément au prompt de reprise, on prend une valeur par défaut, on
la documente, et on construit **derrière l'abstraction**.

## Décision

### Portée des opérateurs (défaut)
Cibler les principaux Mobile Money d'Afrique de l'Ouest / Côte d'Ivoire :
**Orange Money, MTN MoMo, Moov Money, Wave**. Recommandation par défaut : passer
par un **agrégateur** (un PSP fronting plusieurs opérateurs) plutôt que N
intégrations directes, pour réduire la surface d'intégration et de conformité.
Le choix concret de l'agrégateur reste ouvert — l'abstraction le rend
interchangeable.

### Abstraction
Une interface `PaymentProvider` (côté API) définit le contrat : initier un
paiement, vérifier/traiter une notification (webhook), consulter un statut.
- Implémentation par défaut livrée : **`SimulateurPaiement`** (analogue à
  `CanalLog` pour les notifications) — simule le cycle en local/dev/CI, sans
  appeler d'opérateur réel. Elle permet de développer et tester tout le flux.
- Une implémentation « agrégateur réel » sera ajoutée quand la décision et les
  identifiants seront disponibles — sans toucher au cycle de commande ni aux
  contrôleurs (juste une nouvelle classe liée dans le conteneur).

### Machine à états du paiement
`statut_paiement` : `en_attente → initie → regle | echoue | expire`.
`mode_paiement` gagne la valeur `mobile_money` (à côté de `a_la_livraison`).
Le paiement est **découplé** de la livraison (ADR 0004) : une commande peut être
`livree` et `regle` indépendamment ; à la livraison reste le défaut.

### Flux
1. Le foyer choisit de payer une commande `confirmee` par Mobile Money → `initier`
   (le provider renvoie une référence/intention ; en réel : push USSD / lien).
2. L'opérateur notifie le résultat via **webhook** → le backend **vérifie**
   (signature, montant, devise, idempotence) → passe `regle` (ou `echoue`).
3. Un job de **réconciliation** rattrape les paiements en attente (statut
   interrogé au provider).
4. La **commission** (déjà enregistrée à la création) est rapprochée du paiement.

### Sécurité (non négociable — audité)
- Le statut de paiement n'est **jamais** cru depuis le client : seul le webhook
  vérifié (ou la réconciliation) fait foi.
- **Signature** du webhook vérifiée ; rejet sinon. **Idempotence** stricte (une
  notification rejouée ne crédite pas deux fois). **Montant/devise** doivent
  correspondre à la commande. Secrets du provider en variables d'environnement.
- Cloisonnement : un foyer ne paie que ses propres commandes.

## Conséquences

- Tout le flux de paiement est construit et testé derrière `PaymentProvider`, avec
  le simulateur ; brancher un opérateur réel = fournir une implémentation +
  configurer secrets/webhook, sans refonte.
- La décision « agrégateur vs direct » et le choix de l'agrégateur restent à
  trancher ; signalés comme préalable produit.

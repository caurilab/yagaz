# ADR 0004 — Paiement : commande sans paiement en ligne en v1

Date : 2026-09-08
Statut : accepté (sujet ouvert du PROMPT tranché par défaut)

## Contexte

Le PROMPT et le PRD laissent le paiement Mobile Money non tranché. Il faut une
valeur par défaut qui n'engage pas l'avenir et n'empêche pas de brancher le
Mobile Money plus tard.

## Décision

En v1, une commande de recharge se règle **à la livraison** (paiement hors
plateforme). La plateforme gère le cycle commande → livraison sans encaisser.

Un **point d'extension** est isolé dès la conception :

- Une commande porte un champ `mode_paiement` (`a_la_livraison` par défaut) et
  un statut de paiement (`en_attente`, `regle`) découplé du statut de livraison.
- Un contrat d'interface `PaymentProvider` (côté API) est prévu mais non
  implémenté : brancher un provider Mobile Money (Wave, Orange Money, MTN…)
  reviendra à fournir une implémentation, sans toucher au cycle de commande.

## Conséquences

- Aucune intégration de paiement à réaliser en v1 ; pas de dépendance à un PSP.
- La commission sur livraison (modèle économique) est **enregistrée** comme une
  donnée dès la v1, même si elle n'est pas prélevée en ligne — pour l'historique
  et la facturation ultérieure.
- Le jour où le Mobile Money entre, le modèle de données et le cycle de commande
  n'ont pas à être refondus.

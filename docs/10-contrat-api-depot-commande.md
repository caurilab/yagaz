# Contrat d'API v1 — dépôt, commande, livraison, livreur (Phase 4)

*Extension du contrat (`docs/09-contrat-api.md`). Mêmes conventions : Bearer
Sanctum, uuid exposés, enveloppe `data`, 404 hors périmètre, écritures via
FormRequest avec colonnes d'autorisation dérivées serveur (ADR 0005).*

Couvre la boucle **foyer → dépôt → livraison** et le rôle **livreur**. Le
réapprovisionnement dépôt → mandataire relève de la Phase 5.

## 1. Rôles et sélection d'espace

Un compte peut être foyer et/ou membre d'une organisation (dépôt) et/ou livreur.
- `GET /api/mes-roles` → `{ foyer: bool, depots: [{org uuid, nom}], livreur: bool }`.
  L'app s'en sert pour proposer les espaces disponibles (foyer / dépôt / livreur).

## 2. Cycle de vie d'une commande

```
proposee ──(foyer accepte)──> confirmee ──(dépôt prépare)──> preparee
   │  (proposition dépôt/livreur→foyer)        │
   │                                           └──(affectée à un livreur)──> en_livraison
   └──(foyer refuse)──> annulee                                                  │
                                                        (livreur livre)──> livree
```

- Une commande **créée par un foyer** part directement en `confirmee`.
- Une **proposition** (dépôt/livreur vers foyer, « on vous livre ? ») part en
  `proposee` ; le foyer répond (accepte → `confirmee`, refuse → `annulee`).
- `annulee` est atteignable depuis tout état non terminal (par le foyer tant que
  non `preparee`, par le dépôt).
- Le statut de **livraison** (`affectee → en_route → livree → vide_recupere`)
  fait avancer la commande : `en_route` ⇒ commande `en_livraison`,
  `livree` ⇒ commande `livree`.

## 3. Foyer — commander une recharge

| Méthode | Route | Notes |
|---|---|---|
| POST | `/api/commandes` | `{ site_uuid, format_id, quantite, depot_uuid }` → crée une commande `confirmee` (origine foyer). `commission_g` enregistrée (modèle éco), non prélevée (ADR 0004). |
| GET | `/api/commandes` | Commandes du foyer (ses sites), avec statut et suivi de livraison. |
| GET | `/api/commandes/{uuid}` | Détail + livraison associée (statut, livreur). |
| POST | `/api/commandes/{uuid}/reponse` | `{ accepte: bool }` — réponse à une **proposition** (`proposee`) : accepte → `confirmee`, refuse → `annulee`. Réservé au foyer destinataire. |

## 4. Dépôt — traiter les commandes et tenir le stock

Accès : membre `gerant_depot` de l'organisation (policy). Périmètre borné à l'org.

| Méthode | Route | Notes |
|---|---|---|
| GET | `/api/depots/{orgUuid}/stocks` | Stock par format : `pleines`, `vides`, `seuil_plein_bas`, tension éventuelle. |
| PATCH | `/api/depots/{orgUuid}/stocks/{format_id}` | `{ pleines?, vides?, seuil_plein_bas? }` — ajustement manuel (journalisé en `mouvements_stock`). |
| GET | `/api/depots/{orgUuid}/commandes` | File des commandes entrantes des foyers (filtre `?statut`). |
| PATCH | `/api/commandes/{uuid}/preparer` | Dépôt marque `preparee` (décrémente le stock `pleines` du format, mouvement `vente`). |
| POST | `/api/commandes/{uuid}/livraison` | `{ livreur_user_id? }` → crée une `livraison` (`affectee`) et passe la commande en file de livraison. Le livreur doit être rattaché au dépôt. |
| POST | `/api/depots/{orgUuid}/propositions` | `{ site_uuid, format_id, quantite }` → crée une commande `proposee` vers un foyer (le dépôt voit un niveau bas). |

## 5. Livreur — missions

Accès : membre `livreur` (policy). Ne voit que ses livraisons affectées.

| Méthode | Route | Notes |
|---|---|---|
| GET | `/api/livreur/missions` | Livraisons affectées : adresse (site), bouteille/format, quantité, à déposer / à récupérer, statut. Filtre `?statut`. |
| PATCH | `/api/livraisons/{id}/statut` | `{ statut: "en_route" \| "livree" \| "vide_recupere", vides_recuperes? }`. Transitions ordonnées. `livree` propage la commande en `livree` ; `vide_recupere` incrémente les `vides` du dépôt (mouvement `retour_vide`). |

## 6. Règles métier et cohérence

- **Stock** : `preparer` décrémente `pleines` (refus si insuffisant → 422) ;
  `vide_recupere` incrémente `vides`. Chaque variation crée un `mouvement_stock`
  tracé (avec la livraison en référence).
- **Affectation livreur** : le `livreur_user_id` doit être un membre `livreur`
  du dépôt (sinon 422). Un livreur ne voit que ses propres missions.
- **Propagation de statut** : gérée côté serveur, dans une transaction, pour que
  commande et livraison ne divergent jamais. Le foyer voit l'avancement via
  `GET /api/commandes/{uuid}`.
- **Commission** : `commission_g` (ou montant) enregistrée à la création de la
  commande foyer, indépendamment du paiement (à la livraison, ADR 0004).
- **Cloisonnement** : un dépôt ne voit que ses commandes et son stock ; un
  livreur que ses missions ; un foyer que ses commandes. Toute ressource hors
  périmètre → 404.
- **Notifications** (structure de données prête ; envoi réel Phase 5) : une
  proposition de livraison, un changement de statut, génèrent une `alerte` /
  notification vers le foyer concerné.

## 7. Écrans (apps)

- **Dépôt** (mobile) : stock en un coup d'œil (pleines/vides par format, tension),
  file des commandes entrantes (accepter/préparer/affecter), propositions.
- **Livreur** (mobile) : liste des missions (grands boutons, peu de texte),
  transitions de statut en gestes larges, alerte reçue (proposer la livraison).
- **Foyer** (complément) : suivi de commande (statut jusqu'à la livraison),
  réponse à une proposition (oui/non).

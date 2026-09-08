# Contrat d'API — v1 (périmètre foyer)

*Frontière backend (`yagaz-api`) ↔ clients (`yagaz-app`, `yagaz-web`). Toute
évolution passe par l'architecte et est répercutée sur les consommateurs dans la
même phase (voir `.ai/guidelines/collaboration.md`).*

Ce document couvre le **périmètre foyer** (Phase 3). Les endpoints dépôt,
mandataire, distributeur, livreur seront ajoutés aux Phases 4-5.

## Conventions générales

- Base : `/api`. Format : JSON. Encodage UTF-8.
- **Auth** : jeton Bearer (Laravel Sanctum) dans `Authorization: Bearer <token>`.
  Toutes les routes exigent l'auth sauf `auth/register` et `auth/login`.
- **Identifiants exposés** : `uuid` (jamais l'`id` interne) pour users, sites,
  bouteilles, plateaux, commandes.
- **Erreurs** : codes HTTP standards.
  - `401` non authentifié, `403` non autorisé (policy),
  - `422` validation (`{ "message": "...", "errors": { "champ": ["..."] } }`),
  - `404` ressource introuvable ou hors périmètre de l'utilisateur.
- **Cloisonnement** : chaque endpoint applique les policies de la Phase 1. Une
  ressource hors périmètre renvoie `404` (on ne révèle pas son existence).
- **Langue** : les messages respectent `user.langue` (fr par défaut).
- **Ressources** : réponses via API Resources (structure stable, pas de fuite de
  colonnes internes ; `secret_hash`, etc. jamais exposés).

## États visuels de niveau (partagés app/API)

Le niveau brut (`niveau_pct`) est traduit en un **état** commun, pour un langage
visuel identique partout (UX §7) :

| état | condition (indicatif) |
|---|---|
| `plein` | ≥ 75 % |
| `correct` | 40–74 % |
| `bas` | seuil_bas–39 % |
| `presque_vide` | < seuil_bas_pct |
| `inconnu` | pas de mesure récente / pas de plateau |

L'API renvoie l'`etat` calculé ; le client ne recalcule pas les seuils.

## Objet `niveau` (embarqué dans une bouteille)

```json
{
  "gaz_g": 8400,
  "niveau_pct": 67,
  "etat": "correct",
  "autonomie_min": 3360,
  "autonomie_heures": 56,
  "debit_g_par_h": 150,
  "tare_fiable": false,
  "estimation": true,          // true tant que tare non fiable ou débit nominal
  "calcule_at": "2026-09-08T12:30:00Z",
  "frais": true                 // false si la dernière mesure est trop ancienne
}
```

`autonomie_heures` est l'information reine (UX). `estimation: true` déclenche la
mention « estimation en cours d'affinage » côté app.

## Endpoints

### Authentification

| Méthode | Route | Corps | Réponse |
|---|---|---|---|
| POST | `/api/auth/register` | `{ nom, telephone, mot_de_passe, langue? }` | `201 { token, user }` |
| POST | `/api/auth/login` | `{ telephone, mot_de_passe }` | `200 { token, user }` |
| POST | `/api/auth/logout` | — | `204` (révoque le jeton courant) |
| GET | `/api/me` | — | `200 { user }` |

`telephone` est l'identifiant principal (unique). Mot de passe haché (règle de
robustesse minimale). `register` crée un **foyer** (user sans organisation).

### Sites (multi-sites)

| Méthode | Route | Notes |
|---|---|---|
| GET | `/api/sites` | Sites accessibles à l'utilisateur (via `site_acces`), avec `niveau` d'accès, nb de bouteilles, présence d'une alerte active. |
| POST | `/api/sites` | `{ nom, adresse?, lat?, lng? }` → crée le site + un `site_acces` `proprietaire` pour le créateur. |
| GET | `/api/sites/{uuid}` | Détail. `403/404` si pas d'accès. |
| PATCH | `/api/sites/{uuid}` | `{ nom?, adresse?, lat?, lng? }` (gestionnaire/propriétaire). |
| POST | `/api/sites/{uuid}/partages` | `{ telephone, niveau }` → partage l'accès à un utilisateur existant (propriétaire uniquement). Cas « surveiller un proche ». |
| DELETE | `/api/sites/{uuid}/partages/{userUuid}` | Retire un accès (propriétaire). |

### Formats (référentiel, lecture)

| Méthode | Route | Notes |
|---|---|---|
| GET | `/api/formats` | Liste `{ id, code, marque, tare_nominale_g, contenance_gaz_g }` pour l'enregistrement d'une bouteille. |

### Bouteilles

| Méthode | Route | Notes |
|---|---|---|
| GET | `/api/sites/{uuid}/bouteilles` | Bouteilles du site, chacune avec l'objet `niveau`. Triées : active en premier. |
| POST | `/api/sites/{uuid}/bouteilles` | `{ format_id, tare_g?, tare_source?, role_bouteille?, plateau_uid? }`. Enregistre une bouteille ; lie le plateau si `plateau_uid` fourni ; `role_bouteille` défaut `secours` sauf si première bouteille du site → `active`. |
| GET | `/api/bouteilles/{uuid}` | Détail + `niveau`. |
| PATCH | `/api/bouteilles/{uuid}` | `{ role_bouteille?, seuil_bas_pct?, tare_g?, tare_source? }`. Passer `role_bouteille=active` **permute** : l'ancienne active du site repasse `secours` (transaction, respecte l'unique partiel). |
| POST | `/api/bouteilles/{uuid}/plateau` | `{ plateau_uid }` lie un plateau à la bouteille (le plateau doit être `actif` et sur le même site). `DELETE` pour délier. |
| DELETE | `/api/bouteilles/{uuid}` | Supprime la bouteille (gestionnaire). |
| GET | `/api/bouteilles/{uuid}/mesures?depuis=ISO&pas=heure` | Série lissée pour un graphe (lecture ; optionnel Phase 3, utile à la courbe de niveau). |

### Alertes

| Méthode | Route | Notes |
|---|---|---|
| GET | `/api/alertes` | Alertes des sites de l'utilisateur (seuil bas, proposition). Filtre `?statut=emise`. |
| PATCH | `/api/alertes/{id}` | `{ statut: "vue" \| "resolue" }`. |
| PATCH | `/api/me/reglages-alertes` | `{ canaux: ["push","sms","whatsapp"], livreur_habituel? }` (préférences). |

### Recharge (lecture ; commande = Phase 4)

| Méthode | Route | Notes |
|---|---|---|
| GET | `/api/depots?lat=&lng=&format_id=` | Dépôts proches ayant le format en stock (distance, disponibilité). **Lecture seule en Phase 3** ; la création de commande arrive en Phase 4. |

## Règles métier sensibles

- **Permutation active/secours** : au plus une bouteille `active` par site
  (contrainte de données). Le PATCH qui promeut une bouteille démote l'ancienne
  dans la même transaction.
- **Enregistrement de bouteille** : la tare peut être omise (`tare_source` =
  `nominale`) ; le produit fonctionne et s'affine (Phase 2). L'app affiche
  « estimation en cours » tant que `tare_fiable` est faux.
- **Fraîcheur** : `niveau.frais = false` si la dernière mesure dépasse un délai
  (ex. 6 h) — l'app montre « dernière valeur connue » (robustesse réseau).
- **Écriture** : conformément à l'ADR 0005, chaque écriture passe par un
  FormRequest ; les colonnes d'autorisation (`site_id`, appartenance) sont
  dérivées côté serveur, jamais reçues du client.

## Hors ligne (app)

L'app met en cache la dernière réponse `sites`/`bouteilles` (dernier niveau
connu) et l'affiche avec l'état de synchronisation. Les mesures continuent
d'arriver côté serveur ; l'app resynchronise à la reconnexion. Aucune page
blanche : toujours la dernière valeur connue.

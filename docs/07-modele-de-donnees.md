# Modèle de données — Yagaz

*Document de conception — v1. Sert de référence aux migrations (`yagaz-api`).*

Ce document décrit le schéma relationnel (PostgreSQL) et les séries temporelles
(TimescaleDB). Il traduit le PRD et l'architecture en tables, relations et
contraintes. Le **cloisonnement entre acteurs** s'appuie sur des contraintes de
données, pas seulement du code (exigence de sécurité, Phase 1).

## 1. Principes

- **Cloisonnement natif.** Chaque acteur professionnel (dépôt, mandataire,
  distributeur) est une *organisation* ; ses données portent une clé
  d'organisation qui borne tout accès.
- **Multi-sites dès le départ.** Un foyer suit des bouteilles sur plusieurs
  sites, y compris chez un tiers ; l'accès à un site passe par une table de
  droits, jamais par une simple colonne « propriétaire ».
- **Séparation mesure / métier.** Le flux de pesées vit dans une hypertable
  TimescaleDB ; les entités métier vivent dans des tables PostgreSQL classiques.
- **Identifiants.** UUID pour les entités exposées à l'extérieur (plateaux,
  comptes) ; `bigint` auto pour le reste.

## 2. Acteurs, comptes et rôles

### `users`
Le compte de connexion. Un humain = un compte, quel que soit son ou ses rôles.

| Colonne | Type | Notes |
|---|---|---|
| id | bigint PK | |
| uuid | uuid unique | exposé à l'extérieur |
| nom | varchar | |
| telephone | varchar unique | identifiant principal en AO (souvent pas d'email) |
| email | varchar null unique | optionnel |
| mot_de_passe | varchar | haché |
| langue | varchar(5) | défaut `fr` |
| created_at / updated_at | timestamptz | |

### `organisations`
Un acteur professionnel : un dépôt, un mandataire ou un distributeur. C'est la
frontière de cloisonnement (tenant).

| Colonne | Type | Notes |
|---|---|---|
| id | bigint PK | |
| uuid | uuid unique | |
| type | enum | `depot` \| `mandataire` \| `distributeur` |
| nom | varchar | |
| parent_id | bigint null FK organisations | hiérarchie : un dépôt rattaché à un mandataire, un mandataire à un distributeur |
| zone | varchar null | quartier / commune / région selon le type |
| geo | point null | localisation (dépôt) |
| abonnement_actif | boolean | modèle économique (dépôt/mandataire) |
| created_at / updated_at | | |

### `memberships`
Rattache un `user` à une `organisation` avec un rôle. Un même humain peut être
livreur d'un dépôt et gérant d'un autre.

| Colonne | Type | Notes |
|---|---|---|
| id | bigint PK | |
| user_id | bigint FK users | |
| organisation_id | bigint FK organisations | |
| role | enum | `gerant_depot` \| `mandataire` \| `distributeur` \| `livreur` |
| actif | boolean | |
| unique(user_id, organisation_id, role) | | |

> Le rôle **foyer** n'est pas un membership : un foyer est un `user` sans
> organisation, relié à ses sites via `site_acces`. Cela évite de créer une
> « organisation » par foyer.

## 3. Sites et accès (multi-sites)

### `sites`
Une adresse physique où se trouvent des bouteilles.

| Colonne | Type | Notes |
|---|---|---|
| id | bigint PK | |
| uuid | uuid unique | |
| nom | varchar | ex. « Maison », « Chez maman » |
| adresse | varchar null | |
| geo | point null | pour la carte des dépôts proches |
| cree_par | bigint FK users | créateur |
| created_at / updated_at | | |

### `site_acces`
Qui peut voir/gérer quel site, et avec quel niveau. Cœur du multi-sites et du
cloisonnement entre foyers.

| Colonne | Type | Notes |
|---|---|---|
| id | bigint PK | |
| site_id | bigint FK sites | |
| user_id | bigint FK users | |
| niveau | enum | `proprietaire` \| `gestionnaire` \| `observateur` |
| unique(site_id, user_id) | | |

> Surveiller la bouteille d'un parent = un `site_acces` `observateur` (ou
> `gestionnaire`) sur le site du parent. Aucun foyer ne voit un site sans ligne
> d'accès correspondante.

## 4. Matériel et bouteilles

### `plateaux`
Le capteur physique.

| Colonne | Type | Notes |
|---|---|---|
| id | bigint PK | |
| uid | varchar unique | identifiant gravé (`PLT-XXXXXX`), = login MQTT |
| secret_hash | varchar | mot de passe MQTT haché (auth appareil) |
| site_id | bigint null FK sites | où il est installé |
| statut | enum | `provisionne` \| `actif` \| `hors_service` |
| dernier_vu_at | timestamptz null | présence (retained MQTT) |
| firmware_version | varchar null | |
| alim | enum null | `secteur` \| `batterie` |
| created_at / updated_at | | |

### `formats_bouteille`
Référentiel des formats/marques (B6, B12, B24…), avec tare nominale.

| Colonne | Type | Notes |
|---|---|---|
| id | bigint PK | |
| code | varchar | `B6`, `B12`, `B24` |
| marque | varchar | Total, Oryx… |
| tare_nominale_g | integer | poids à vide gravé, indicatif |
| contenance_gaz_g | integer | masse de gaz quand plein (ex. B12 ≈ 12 000 g) |
| unique(code, marque) | | |

### `bouteilles`
Une bouteille suivie par un foyer, posée (ou non) sur un plateau.

| Colonne | Type | Notes |
|---|---|---|
| id | bigint PK | |
| uuid | uuid unique | |
| site_id | bigint FK sites | |
| format_id | bigint FK formats_bouteille | |
| plateau_id | bigint null FK plateaux | la bouteille active d'un plateau ; null si non posée |
| tare_g | integer null | tare **propre** à cette bouteille |
| tare_source | enum | `nominale` \| `saisie` \| `calibree` |
| tare_fiable | boolean | true quand le calibrage auto la juge stable |
| role_bouteille | enum | `active` \| `secours` |
| seuil_bas_pct | integer | seuil d'alerte choisi par le foyer (défaut 15) |
| created_at / updated_at | | |

> **Tare** (point sensible du PRD) : `tare_source` + `tare_fiable` pilotent
> l'affichage (« mesure en cours d'affinage » tant que non fiable). Le calibrage
> auto observe le poids plancher sur plusieurs cycles (Phase 2).
>
> **Active vs secours** : `role_bouteille`. Une contrainte partielle garantit au
> plus une bouteille `active` par site (index unique partiel).

## 5. Mesures (TimescaleDB)

### `mesures` — hypertable
Chaque pesée reçue d'un plateau. Partitionnée par temps (hypertable).

| Colonne | Type | Notes |
|---|---|---|
| plateau_id | bigint FK plateaux | |
| bouteille_id | bigint null FK bouteilles | résolue à l'ingestion |
| mesure_at | timestamptz | horodatage plateau (ADR 0003, `ts`) |
| recu_at | timestamptz | horodatage serveur |
| poids_g | integer | poids brut |
| gaz_g | integer null | = poids_g − tare, calculé (null si tare inconnue) |
| seq | bigint | anti-doublon/anti-trou |
| batt_mv / rssi / temp_c | | télémétrie optionnelle |
| PK (plateau_id, mesure_at) | | |

- `SELECT create_hypertable('mesures', 'mesure_at')`.
- Agrégat continu (Phase 2) : moyenne lissée par heure, base du calcul de débit.
- Rétention : brut fin conservé N mois, agrégats au-delà (à cadrer devops).
- Unicité logique `(plateau_id, seq)` pour l'idempotence (vérifiée à l'ingestion).

### `niveaux_courants`
Vue matérialisée / table de cache du dernier état par bouteille (pour un
affichage instantané et le fonctionnement hors ligne côté app).

| Colonne | Type | Notes |
|---|---|---|
| bouteille_id | bigint PK FK bouteilles | |
| gaz_g | integer | dernière masse de gaz |
| niveau_pct | integer | 0–100 |
| autonomie_min | integer null | autonomie estimée en minutes de flamme |
| debit_g_par_h | numeric null | débit observé récent |
| calcule_at | timestamptz | |

## 6. Commande et livraison

### `commandes`
Une demande de recharge (émise par un foyer, ou un dépôt vers son mandataire).

| Colonne | Type | Notes |
|---|---|---|
| id | bigint PK | |
| uuid | uuid unique | |
| origine | enum | `foyer` \| `depot` |
| demandeur_user_id | bigint null FK users | si foyer |
| demandeur_org_id | bigint null FK organisations | si dépôt |
| cible_org_id | bigint FK organisations | dépôt (si foyer) / mandataire (si dépôt) |
| site_id | bigint null FK sites | livraison foyer |
| format_id | bigint FK formats_bouteille | |
| quantite | integer | |
| statut | enum | `proposee` \| `confirmee` \| `preparee` \| `en_livraison` \| `livree` \| `annulee` |
| mode_paiement | enum | `a_la_livraison` (défaut, ADR 0004) |
| statut_paiement | enum | `en_attente` \| `regle` |
| commission_g | integer null | commission enregistrée (modèle éco), même non prélevée |
| created_at / updated_at | | |

### `livraisons`
L'exécution physique d'une commande par un livreur.

| Colonne | Type | Notes |
|---|---|---|
| id | bigint PK | |
| commande_id | bigint FK commandes | |
| livreur_user_id | bigint null FK users | affecté |
| tournee_id | bigint null FK tournees | |
| statut | enum | `affectee` \| `en_route` \| `livree` \| `vide_recupere` |
| pleines_deposees | integer | |
| vides_recuperes | integer | logistique inversée |
| horodatages | timestamptz… | par transition de statut |

### `tournees`
Regroupe des livraisons d'un mandataire (ou dépôt) pour une journée.

| Colonne | Type | Notes |
|---|---|---|
| id | bigint PK | |
| organisation_id | bigint FK organisations | mandataire/dépôt qui organise |
| livreur_user_id | bigint null FK users | |
| date | date | |
| statut | enum | `proposee` \| `validee` \| `en_cours` \| `terminee` |

## 7. Stocks

### `stocks`
État du stock d'une organisation (dépôt surtout), par format, plein et vide.

| Colonne | Type | Notes |
|---|---|---|
| id | bigint PK | |
| organisation_id | bigint FK organisations | |
| format_id | bigint FK formats_bouteille | |
| pleines | integer | |
| vides | integer | |
| seuil_plein_bas | integer | déclenche la préparation de réappro |
| unique(organisation_id, format_id) | | |

### `mouvements_stock`
Journal des variations (vente, retour vide, réappro), pour l'historique et
l'audit.

| Colonne | Type | Notes |
|---|---|---|
| id | bigint PK | |
| stock_id | bigint FK stocks | |
| type | enum | `vente` \| `retour_vide` \| `reappro` \| `ajustement` |
| delta_pleines / delta_vides | integer | |
| livraison_id | bigint null FK livraisons | trace |
| created_at | | |

## 8. Alertes et notifications

### `alertes`
Trace des franchissements de seuil et propositions, pour éviter le re-spam.

| Colonne | Type | Notes |
|---|---|---|
| id | bigint PK | |
| bouteille_id | bigint null FK | |
| organisation_id | bigint null FK | tension de stock |
| type | enum | `seuil_bas` \| `proposition_livraison` \| `stock_tension` |
| statut | enum | `emise` \| `vue` \| `resolue` |
| canal | enum | `push` \| `sms` \| `whatsapp` |
| created_at | | |

### `livreur_habituel`
Le foyer peut désigner un livreur prévenu automatiquement au seuil bas.

| Colonne | Type | Notes |
|---|---|---|
| site_id | bigint FK sites | |
| livreur_user_id | bigint FK users | |
| actif | boolean | |

## 9. Agrégats distributeur

Le distributeur ne voit **jamais** de donnée individuelle de foyer. Il lit des
agrégats construits par des agrégations continues TimescaleDB / jobs :
consommation par zone, par format, dans le temps. Ces vues agrégées portent une
clé de zone, jamais une clé de foyer. (Détail en Phase 5.)

## 10. Cloisonnement — règles transverses

1. Toute requête d'un acteur pro est bornée par son `organisation_id` (et la
   hiérarchie `parent_id` pour mandataire → dépôts).
2. Tout accès foyer à un site passe par `site_acces`.
3. Un plateau ne peut écrire que des mesures pour son propre `uid` (auth MQTT +
   contrôle à l'ingestion).
4. Les FK et index uniques matérialisent ces frontières ; le code applicatif
   (policies Laravel) s'appuie dessus, il ne les remplace pas.

## 11. Ordre de création (migrations)

1. `users`, `organisations`, `memberships`
2. `sites`, `site_acces`
3. `formats_bouteille`, `plateaux`, `bouteilles`
4. `mesures` (hypertable), `niveaux_courants`
5. `stocks`, `mouvements_stock`
6. `commandes`, `tournees`, `livraisons`
7. `alertes`, `livreur_habituel`

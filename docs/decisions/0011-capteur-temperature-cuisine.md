# ADR 0011 — Capteur de température de cuisine

Date : 2026-09-08
Statut : accepté

## Contexte

Nouveau matériel demandé : un **capteur de température** placé dans la cuisine,
près du point de cuisson. Objectifs :
1. **Détecter la cuisson en cours** (le gaz est allumé) → l'utilisateur voit
   « cuisson en cours », et on gagne en **traçabilité** (quand, combien de temps).
2. **Sécurité** : si la température devient trop élevée, alerter (« baissez le
   gaz, température trop élevée ») pour prévenir un risque.
3. Alimenter les **analyses** (jours/heures de cuisine, corrélation avec la
   consommation de gaz).

## Décision

### Matériel
Un capteur de température ambiante (ex. DS18B20 ou thermistance) rattaché au
plateau ESP32 existant, OU un petit module dédié. En v1 : le plateau publie une
**mesure de température** en plus du poids (le firmware lit un capteur de temp
ambiante distinct de la température interne de l'électronique).

### Transport (extension ADR 0003)
Topic dédié : `yagaz/v1/plateau/{uid}/temperature`, JSON compact :
```json
{ "v": 1, "ts": 1725792000, "temp_c": 47.5, "seq": 12 }
```
(Champ `temp_c` = température de la cuisine, °C. `seq` pour dédup, comme les mesures.)

### Données
- Table `temperatures` (série temporelle ; hypertable TimescaleDB en prod, table
  classique sur SQLite) : `plateau_id`, `site_id`, `mesure_at`, `recu_at`,
  `temp_c`, `seq`.
- **Sessions de cuisson** `sessions_cuisson` : `site_id`, `debut_at`, `fin_at`
  (null tant qu'en cours), `temp_max_c`. Ouverte quand la température dépasse
  `seuil_cuisson_c` de façon soutenue ; fermée quand elle redescend.

### Logique d'ingestion
- Ranger chaque température (dédup par `seq`).
- **Détection de cuisson** : si `temp_c >= seuil_cuisson_c` (défaut 40 °C) et
  qu'aucune session ouverte → ouvrir une session (`cuisson en cours`). Si
  `temp_c < seuil_cuisson_c` de façon soutenue → fermer la session.
- **Alerte sécurité** : si `temp_c >= seuil_danger_c` (défaut 60 °C, ajustable) →
  alerte `temperature_elevee` adressée au foyer (anti-spam : pas de doublon tant
  que non résolue / la température n'est pas redescendue).
- Le `niveaux_courants` (ou un cache dédié) expose la **température courante** et
  l'état `cuisson_en_cours` pour l'affichage instantané.

### Constantes (ajustables, config `mesure.php`)
| Constante | Défaut | Sens |
|---|---|---|
| `seuil_cuisson_c` | 40 | au-dessus : cuisson considérée en cours |
| `seuil_danger_c` | 60 | au-dessus : alerte sécurité |
| `duree_min_cuisson_s` | 120 | anti-rebond (température soutenue) |

## Conséquences

- Le firmware publie une température ambiante ; le backend en déduit cuisson +
  sécurité + traçabilité, et l'app l'affiche (température, « cuisson en cours »,
  historique des sessions, alerte sécurité).
- Les seuils sont des valeurs de départ à calibrer avec le matériel réel.
- Aucune donnée de température n'est exposée hors du périmètre du foyer
  (cloisonnement inchangé) ; l'agrégat distributeur reste sans donnée individuelle.

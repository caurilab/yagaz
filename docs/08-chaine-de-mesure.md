# Chaîne de mesure — de la pesée à l'autonomie

*Document de conception — Phase 2. Référence pour l'ingestion (`yagaz-api`) et le
firmware (`yagaz-firmware`).*

Ce document décrit ce qui se passe entre le moment où un plateau publie un poids
et l'affichage d'une autonomie fiable côté foyer : ingestion, robustesse,
gestion de la tare, conversion poids → niveau → autonomie, alertes.

## 1. Le trajet d'une mesure

```
Plateau ESP32 ──MQTT──> Broker ──> Worker d'ingestion (Laravel)
   (poids brut)   (ADR 0003)         │
                                     ├─ 1. authentifier / valider l'appareil
                                     ├─ 2. décoder + valider le message
                                     ├─ 3. dédupliquer (seq)
                                     ├─ 4. filtrer le bruit / les aberrations
                                     ├─ 5. ranger la mesure (hypertable)
                                     ├─ 6. affiner la tare (calibrage auto)
                                     ├─ 7. recalculer niveau + autonomie
                                     └─ 8. déclencher les alertes si seuil franchi
```

Le worker est un consommateur MQTT découplé du REST (architecture §3). Il tourne
en process long (`php artisan yagaz:ingest`), supervisé (supervisor/horizon en
prod). Le traitement lourd (étapes 6-8) peut partir en file pour ne pas bloquer
la consommation du flux.

## 2. Authentification et cloisonnement de l'appareil

- Chaque plateau se connecte au broker avec `username = uid`, `password = secret`
  (le `secret_hash` en base est vérifié via `Hash::check` au provisioning ; le
  broker gère l'auth MQTT côté transport).
- **ACL Mosquitto** : un plateau ne peut publier que sur `yagaz/v1/plateau/{son
  uid}/#`. Un plateau compromis ne peut donc pas usurper un autre plateau.
- À l'ingestion, si l'`uid` du topic est inconnu ou non `actif`, le message est
  **rejeté et journalisé** (jamais rangé). Un plateau compromis ne pollue pas les
  données des autres (exigence Phase 2).

## 3. Validation et robustesse

Règles appliquées à chaque message (dans l'ordre) :

1. **Format** : `v`, `ts`, `poids_g`, `seq` présents et typés (ADR 0003). Sinon rejet.
2. **Horloge** : si `ts` dérive de plus de ±1 h par rapport à l'heure serveur,
   on borne `mesure_at` et on conserve `recu_at` ; on journalise la dérive.
3. **Doublon / trou** : `seq` déjà vu (ou en régression) ⇒ doublon, ignoré
   (idempotence portée par la clé `(plateau_id, mesure_at, seq)`). Un saut de
   `seq` signale un trou (messages perdus) : on l'accepte, on note le trou.
4. **Aberration physique** :
   - `poids_g < 0` ou `poids_g > POIDS_MAX_ABSOLU` (60 kg) ⇒ rejet.
   - **Bouteille retirée** : `poids_g` proche de 0 (< seuil « plateau nu »)
     ⇒ état `sans_bouteille`, pas de calcul d'autonomie.
   - **Saut transitoire** : variation brutale non soutenue (quelqu'un s'appuie,
     plateau bousculé) ⇒ la mesure brute est rangée, mais le **niveau lissé**
     l'ignore (voir §4).

## 4. Lissage

La mesure brute est toujours stockée (traçabilité, recalcul). Pour l'affichage,
on lisse : moyenne mobile exponentielle (EMA) sur les dernières mesures stables,
ou médiane glissante pour rejeter les pics. Le poids lissé sert au niveau et à
l'autonomie ; jamais la dernière valeur brute seule.

## 5. La tare (point sensible du PRD)

La tare est le poids à vide de la bouteille — propre à chaque bouteille. On doit
fonctionner même si elle est approximative, en s'affinant.

**Deux sources initiales** (`tare_source`) :
- `nominale` : la tare du format (référentiel), utilisée par défaut.
- `saisie` : l'utilisateur renseigne la tare gravée sur la collerette
  (saisie assistée, éventuellement par photo/scan côté app).

**Calibrage automatique par le poids plancher** (`calibree`) :
- On maintient le **minimum stable observé** du poids (sur une fenêtre, en
  écartant les valeurs non stabilisées). Physiquement, une bouteille ne peut pas
  peser moins que sa tare : si un poids stable inférieur à la tare courante
  apparaît, c'est que la tare était surestimée ⇒ on l'abaisse vers ce plancher.
- Après avoir observé un plancher stable de façon répétée (≥ `N_CALIBRAGE`
  observations sur des cycles distincts), on marque `tare_fiable = true`.
- Garde-fou : la tare reste bornée à une plage plausible autour du nominal
  (nominal ± marge) pour rejeter un plancher aberrant.

**Affichage tant que la tare n'est pas fiable** : le niveau et l'autonomie sont
montrés avec une mention « estimation en cours d'affinage » (côté app), plutôt
que de bloquer l'utilisateur. La valeur se précise avec l'usage.

## 6. Niveau

```
gaz_g      = max(0, poids_lisse_g − tare_g)
niveau_pct = clamp( round(gaz_g / contenance_gaz_g × 100), 0, 100 )
```

`contenance_gaz_g` vient du format (ex. B12 ≈ 12 500 g). Le niveau est borné
[0, 100] : une tare légèrement fausse ne produit ni négatif ni > 100 %.

## 7. Autonomie en heures de flamme

L'information reine côté foyer (UX §7). Voir **ADR 0006** pour le modèle retenu.

En résumé v1 :
```
autonomie_heures = gaz_g / debit_flamme_g_par_h
```
- `debit_flamme_g_par_h` démarre à une valeur **nominale** (ADR 0006, 150 g/h)
  et se raffine en observant les pentes de consommation soutenues (flamme
  allumée) dans l'historique TimescaleDB.
- Tant que l'historique est insuffisant, on utilise le nominal et on signale une
  estimation.
- `debit_g_par_h` observé est stocké dans `niveaux_courants` pour l'affichage et
  le raffinement.

`niveaux_courants` est mis à jour à chaque mesure retenue : `gaz_g`, `niveau_pct`,
`autonomie_min`, `debit_g_par_h`, `calcule_at`. C'est la table lue par l'app
(affichage instantané + dernier état connu hors ligne).

## 8. Alertes de seuil

- Quand `niveau_pct` (bouteille **active**) franchit `seuil_bas_pct` à la baisse,
  on émet une alerte `seuil_bas` — une seule fois par franchissement (anti-spam
  via la table `alertes` : pas de nouvelle alerte tant que le niveau n'est pas
  remonté au-dessus du seuil).
- La bouteille de **secours** ne déclenche pas d'alerte de la même façon
  (sobriété : on ne réveille pas pour une réserve).
- Si un `livreur_habituel` est actif sur le site, l'alerte peut déclencher une
  proposition de livraison (Phase 4).

## 9. Agrégats (préparation Phase 5)

Les agrégations continues TimescaleDB (consommation par zone/format/temps) ne
sont pas créées ici mais prévues : elles alimenteront la vue distributeur en
Phase 5, à partir de la même hypertable, sans jamais exposer de donnée
individuelle de foyer.

## 10. Constantes (valeurs de départ, ajustables)

| Constante | Valeur | Sens |
|---|---|---|
| `POIDS_MAX_ABSOLU` | 60 000 g | rejet aberration haute |
| `SEUIL_PLATEAU_NU` | 1 000 g | en dessous : pas de bouteille |
| `N_CALIBRAGE` | 5 | observations plancher avant `tare_fiable` |
| `MARGE_TARE` | 2 000 g | plage plausible autour du nominal |
| `debit_flamme_nominal` | 150 g/h | ADR 0006 |
| `FENETRE_DEBIT` | 14 jours | historique pour estimer le débit observé |

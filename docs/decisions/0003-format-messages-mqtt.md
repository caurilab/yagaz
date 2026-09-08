# ADR 0003 — Format des messages MQTT du plateau

Date : 2026-09-08
Statut : accepté (v1, sujet ouvert du PROMPT tranché par défaut)

## Contexte

Le PROMPT laisse ouvert « le format précis des messages MQTT » avec consigne de
décider un format simple et versionné, et de le documenter. Ce format est un
contrat entre le firmware ESP32 (`yagaz-firmware`) et le worker d'ingestion
Laravel (`yagaz-api`). Il doit être léger (appareils faible puissance, réseau
instable), versionné (évolutivité), et permettre de détecter trous et doublons.

## Décision

### Topics

```
yagaz/v1/plateau/{uid}/mesure   # QoS 1 — le plateau publie ses pesées
yagaz/v1/plateau/{uid}/etat     # retained — présence en ligne/hors ligne (LWT)
yagaz/v1/plateau/{uid}/cmd      # le backend pousse une commande au plateau
```

- `{uid}` : identifiant unique du plateau, immuable, gravé au provisioning
  (ex. `PLT-7Q3F2A`). Un plateau ne publie QUE sur son propre `{uid}` (ACL
  Mosquitto ajoutée en Phase 2).
- **QoS 1** sur `mesure` : au moins une livraison ; les doublons sont gérés
  côté backend via `seq`.
- Le message d'état est un *retained last-will* : `{"online": false}` publié
  automatiquement par le broker si le plateau tombe.

### Charge utile `mesure` (JSON, compact)

```json
{
  "v": 1,
  "ts": 1725792000,
  "poids_g": 12345,
  "seq": 42,
  "batt_mv": 3700,
  "rssi": -67,
  "temp_c": 28.5
}
```

| Champ | Type | Obligatoire | Sens |
|---|---|---|---|
| `v` | entier | oui | Version du format (1). |
| `ts` | entier | oui | Horodatage de la mesure, epoch **secondes UTC** (heure du plateau). |
| `poids_g` | entier | oui | Poids brut mesuré, en **grammes** (bouteille + gaz + tare). |
| `seq` | entier | oui | Compteur monotone par plateau ; détecte trous et doublons. |
| `batt_mv` | entier | non | Tension batterie en mV (absent si sur secteur). |
| `rssi` | entier | non | Qualité du signal Wi-Fi (dBm). |
| `temp_c` | nombre | non | Température du capteur, pour compenser la dérive thermique. |

### Règles de traitement (backend)

- Un message dont le `{uid}` est inconnu ou non provisionné est **rejeté** et journalisé.
- `seq` en régression ou déjà vu ⇒ **doublon**, ignoré (idempotence).
- Un `ts` trop dans le futur/passé (dérive d'horloge) est **borné** et signalé ;
  l'horodatage de réception serveur est conservé en parallèle.
- Les valeurs aberrantes (poids négatif, saut physiquement impossible) sont
  filtrées par la logique métier (Phase 2), pas au niveau du transport.

## Conséquences

- Le firmware et le backend partagent ce contrat ; toute évolution incrémente
  `v` et se répercute des deux côtés dans la même phase.
- Le grammage entier évite les flottants côté firmware (plus robuste, plus léger).
- Le format est extensible : ajouter un champ optionnel ne casse pas `v1`.

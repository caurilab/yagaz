# ops — Infrastructure locale Yagaz

Tout le nécessaire pour lancer l'environnement de développement : base de
données (Postgres + TimescaleDB), broker MQTT (Mosquitto) et Redis, via
Docker Compose.

## Prérequis

- Docker Desktop installé et lancé.

## Démarrage

```bash
# 1. Copier le contrat d'environnement racine
cp .env.example .env
# (éditer .env : remplacer les mots de passe change_me_local)

# 2. Générer le fichier de mots de passe Mosquitto (hors dépôt)
#    On crée le compte de service d'ingestion utilisé par le worker Laravel.
docker run --rm eclipse-mosquitto:2 \
  mosquitto_passwd -b -c /tmp/passwd yagaz-ingest 'change_me_local' \
  > ops/mosquitto/passwd
# (ou, si mosquitto est installé en local :
#   mosquitto_passwd -b -c ops/mosquitto/passwd yagaz-ingest 'change_me_local')

# 3. Lancer l'infra
docker compose up -d

# 4. Vérifier
docker compose ps
```

## Services

| Service | Port | Rôle |
|---|---|---|
| `db` (TimescaleDB/pg17) | 5432 | Données métier + séries temporelles des mesures |
| `mqtt` (Mosquitto 2) | 1883 / 9001 | Ingestion des mesures des plateaux (TCP / WebSocket) |
| `redis` | 6379 | Files d'attente Laravel |

## Notes de sécurité

- `ops/mosquitto/passwd` et `.env` ne sont **jamais** commités (voir `.gitignore`).
- Mosquitto n'autorise pas l'accès anonyme et applique une **ACL par appareil**
  (`ops/mosquitto/acl`) : chaque plateau ne peut publier que sur son propre topic
  `yagaz/v1/plateau/{son uid}/...`. Seul le compte de service `yagaz-ingest`
  écoute l'ensemble des mesures.
- **Provisionner un plateau** : ajouter son couple identifiants au broker
  (`mosquitto_passwd -b ops/mosquitto/passwd <uid> <secret>`) et créer
  l'enregistrement `Plateau` correspondant en base (statut `actif`, `secret_hash`
  haché). L'ingestion rejette tout `uid` non provisionné.
- Les mots de passe `change_me_local` sont pour le développement uniquement.

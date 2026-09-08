# ADR 0007 — Sécurité de l'ingestion : transport et exploitation

Date : 2026-09-08
Statut : accepté (suite à l'audit sécurité Phase 2)

## Contexte

L'audit de la chaîne d'ingestion (Phase 2) a confirmé que le modèle de confiance
est sain — l'`uid` fait foi via le topic (l'ACL Mosquitto empêche un plateau de
publier ailleurs que sur le sien), le blast radius d'un plateau compromis reste
borné à sa propre bouteille — mais a relevé trois risques d'exploitation :
transport en clair, reset de `seq`, et empoisonnement de la tare. Les deux
derniers sont corrigés dans le code (voir rapport de Phase 2). Cet ADR fixe la
posture de transport et d'exploitation.

## Décision

### Transport
- **Développement** : MQTT en clair (1883 / 9001 WS) est acceptable, à condition
  de rester sur un réseau local de confiance.
- **Production** : le broker DOIT exposer un listener **TLS (8883)** et le
  listener en clair DOIT être désactivé. Le mTLS par appareil (certificat client
  par plateau) est recommandé à terme ; a minima TLS serveur + identifiants.
  Tant que le TLS n'est pas en place, le broker DOIT être confiné à un réseau de
  confiance (VPN / réseau privé). La configuration est préparée (commentée) dans
  `ops/mosquitto/mosquitto.conf`.

### Exploitation du worker
- Le service `TraitementMesure` suppose un traitement **séquentiel par plateau**.
  En v1, on exploite **un seul worker** `yagaz:ingest` (pas de scale-out).
- Si un scale-out devient nécessaire, la sérialisation par `plateau_id` (file à
  clé, ou verrou/transaction autour de la section critique) est **obligatoire**
  avant de lancer plusieurs workers, sinon races sur `niveaux_courants`, le
  compteur de calibrage et l'anti-spam d'alerte.

### Robustesse
- Les entrées sont bornées (`seq`, `ts`, taille du message côté broker) et les
  logs assainis (jamais le payload brut complet) — corrigé dans le code.

## Conséquences

- Prérequis de déploiement documenté : TLS ou réseau confiné.
- Un runbook de provisioning des plateaux (identifiants broker + enregistrement
  `Plateau` + à terme certificat) sera établi par l'agent devops avant la mise en
  production.
- La montée en charge de l'ingestion est un sujet explicitement tracé (pas de
  multi-worker sans sérialisation).

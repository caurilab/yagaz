# Rapport final — Yagaz v1

Date : 2026-09-08
Dépôt : `github.com/caurilab/yagaz` (branche `main`, CI verte)

Ce rapport clôt le cycle de développement autonome demandé : construction phase
par phase, sécurité et tests à chaque incrément, décisions tracées en ADR. Il
récapitule ce qui a été construit, les décisions prises, l'état des tests et de
la sécurité, et se termine par une liste de recommandations pour la suite.

## 1. Ce qui a été construit, phase par phase

### Phase 0 — Fondations
Monorepo Git, quatre sous-projets scaffoldés (API Laravel 13, web React 19, app
Expo/React Native, firmware ESP32), infra locale Docker (TimescaleDB + Mosquitto
+ Redis), CI GitHub Actions, auth Sanctum, secrets hors dépôt.

### Phase 1 — Modèle de données et cloisonnement
21 migrations (17 modèles, 17 enums), hypertable TimescaleDB pour les mesures,
multi-sites via `site_acces`, cloisonnement des acteurs par policies adossées à
des contraintes de données. Audit sécurité (mass-assignment des tables
d'autorisation, etc.) mené et corrigé.

### Phase 2 — Ingestion et chaîne de mesure
Service d'ingestion MQTT (`yagaz:ingest`) : validation, déduplication, filtrage
des aberrations, lissage, **calibrage automatique de la tare**, calcul du niveau
et de l'autonomie, alertes. Firmware ESP32 (HX711 + MQTT). ACL Mosquitto par
appareil. Audit sécurité corrigé (dont un **anti panne-sèche critique** : une
tare faussée ne peut plus masquer l'alerte de niveau bas).

### Phase 3 — Application foyer
Contrat d'API v1. Endpoints backend (auth, sites, bouteilles, formats, alertes,
dépôts proches). App React Native : accueil avec l'**autonomie en heures** en
héros, multi-sites, enregistrement guidé, hors ligne robuste. Audit corrigé
(rate-limiting, normalisation E.164 du téléphone).

### Phase 4 — Dépôt et boucle de commande
Cycle commande/livraison transactionnel (machine à états centralisée), stock et
mouvements, espaces **dépôt** et **livreur** dans l'app, suivi côté foyer. Audit
corrigé (races TOCTOU, bornes, propositions restreintes).

### Phase 5 — Mandataire et distributeur
Vue consolidée + tournées mandataire (web + mobile), **agrégats régionaux
distributeur étanches au foyer**, service de notifications abstrait. Dashboards
web React 19 (recharts). Audit corrigé (borne de dates, throttle, invariant
anti-fuite du notificateur).

## 2. Décisions structurantes (ADR)

| ADR | Décision |
|---|---|
| 0001 | Monorepo (un seul dépôt, sous-projets = dossiers) |
| 0002 | Infra locale via Docker Compose (TimescaleDB + Mosquitto + Redis) |
| 0003 | Format des messages MQTT (topic versionné, JSON compact, QoS, `seq`) |
| 0004 | Paiement : commande sans paiement en ligne en v1 (à la livraison), point d'extension isolé |
| 0005 | Posture d'écriture / mass-assignment (FormRequest + colonnes d'autorisation dérivées serveur) |
| 0006 | Modèle d'autonomie (débit flamme nominal 150 g/h, raffiné par observation) |
| 0007 | Sécurité de l'ingestion (TLS en prod, ingestion mono-worker) |

Décision de socle confirmée avec le porteur (conflit CLAUDE.md global vs cadrage)
tranchée pour le cadrage : **React 19** (web), **React Native/Expo** (mobile).

## 3. Sujets ouverts et valeurs par défaut retenues

- **Paiement Mobile Money** (laissé ouvert par le prompt) → paiement à la
  livraison en v1, `PaymentProvider` isolé (ADR 0004).
- **Format des messages MQTT** (laissé ouvert) → tranché et documenté (ADR 0003).
- **Modèle d'autonomie** → débit flamme nominal, raffiné par l'observation des
  pentes (ADR 0006) ; valeurs de seuils à affiner avec les données du prototype.
- **Alimentation du plateau** (secteur/batterie) → à trancher au prototypage ;
  le firmware prévoit un échantillonnage adaptatif dans les deux cas.

## 4. État des tests

- **131 tests, 442 assertions**, verts. Backend d'abord (cloisonnement,
  ingestion, boucle de commande, agrégats, notifications).
- **CI GitHub Actions** verte à chaque push : migrations exécutées sur un **vrai
  TimescaleDB** (hypertable), suite de tests sur SQLite en mémoire, build de
  production du web, typecheck de l'app.
- Le firmware n'est pas compilé dans cet environnement (pas de toolchain
  PlatformIO) : vérifié par lecture, `pio run` à faire à l'atelier.

## 5. Sécurité traitée au fil de l'eau

La sécurité a été auditée **à chaque phase** (audits Opus dédiés), pas en fin de
projet. Points traités :
- Cloisonnement des acteurs par policies + contraintes de données ; un acteur pro
  ne voit jamais une mesure/niveau de foyer ; le distributeur ne voit que des
  agrégats (étanchéité vérifiée par test).
- Mass-assignment verrouillé sur les tables d'autorisation ; écritures via
  FormRequest avec colonnes d'autorisation dérivées serveur.
- Auth : rate-limiting (login/register/réglages), normalisation du téléphone,
  login constant-time.
- Ingestion : authentification d'appareil (ACL MQTT), rejet des `uid` non
  provisionnés, anti panne-sèche indépendant de la tare, gestion du reset de
  `seq`, bornage des entrées, logs assainis.
- Boucle de commande : machine à états serveur, races TOCTOU fermées (verrous +
  index unique), bornes anti-débordement.
- Secrets hors dépôt (vérifié) ; dépôt public sans fuite.

## 6. Recommandations pour la suite

> Cette liste est à passer en revue ensemble ; elle n'a pas été traitée dans
> cette session. Voir aussi la revue produit (Phase 6) intégrée ci-dessous.

### Mise en production (bloquants avant prod)
1. **TLS MQTT** (ADR 0007) : activer le listener 8883, désactiver le clair, ou
   confiner à un réseau privé documenté.
2. **Infra réelle** : démarrer TimescaleDB/Mosquitto/Redis (Docker ou managé),
   exécuter les migrations, runbook de **provisioning des plateaux** (identifiants
   broker + enregistrement `Plateau`).
3. **Canaux de notification réels** : implémenter `CanalNotification` pour push
   (FCM/APNs), SMS et WhatsApp (aujourd'hui `CanalLog`).

### Fiabilité de la mesure (cœur produit)
4. **Calibrer avec le prototype** : ajuster les constantes (tolérances de
   plancher, bornes de débit, fréquences) sur de vraies bouteilles B6/B12/B24.
5. **Raffiner l'autonomie** : itérer la détection des pentes « flamme allumée »
   avec des données réelles ; envisager les agrégations continues TimescaleDB.
6. **Firmware** : compiler/flasher, valider la chaîne de pesée, décider
   secteur/batterie, évaluer un vrai QoS 1 MQTT si les pertes le justifient.

### Sécurité complémentaire
7. **Audit de sécurité externe** avant lancement (pentest), notamment l'ingestion
   et l'auth.
8. **k-anonymat** des agrégats distributeur : seuil de suppression optionnel si
   la granularité-quantité est jugée sensible.
9. **Défense en profondeur** : compléter les `$fillable` explicites sur les
   modèles restants au fil de leurs endpoints d'écriture.

### Complétude produit (revue produit Phase 6)

La revue produit confirme que **la chaîne descendante tient de bout en bout**
(foyer → dépôt → livreur → retour de vide, statuts remontés) et que la lecture
**distributeur** est complète et étanche au foyer. Le point à compléter est le
**déclenchement de la chaîne par le niveau bas** (concept central, PRD §7), qui
ne se referme pas encore. Manques réels priorisés :

10. **Notification du livreur habituel au seuil bas** : `livreur_habituel_user_id`
    est stocké mais jamais consommé par l'ingestion. Câbler la notification (en
    respectant l'invariant anti-fuite : décider le minimum d'info transmis à un
    livreur tiers). *(Foyer §5.1 + Livreur §5.5)*
11. **Boucle réappro dépôt → mandataire** : rien ne crée de commande
    `origine=depot` vers le mandataire ; `GET /mandataires/{org}/reappros` renvoie
    toujours vide. Livrer : préparation auto du réappro à partir des stocks en
    tension, écran dépôt de confirmation/ajustement, endpoint de réponse dépôt.
    *(Dépôt §5.2 + Mandataire §5.3)*
12. **Flux livreur « alerte → proposer la livraison »** : l'espace livreur n'a que
    les missions ; pas de réception de notification ni d'action de proposition
    (aujourd'hui `POST /depots/{org}/propositions` est réservé au gérant). *(Livreur §5.5)*
13. **File des foyers en tension côté dépôt** : la proposition dépôt→foyer existe
    mais est inutilisable (le dépôt doit saisir l'UUID d'un site qu'il ne peut pas
    connaître). Fournir une file des foyers en tension desservis par le dépôt.
14. **Multilingue** : seul `user.langue` est stocké ; aucune couche i18n (app/web)
    ni `lang/` backend. Introduire i18n et brancher la langue.
15. **Tare par photo/scan de la collerette** (Foyer §5.1) : aujourd'hui saisie
    manuelle + calibrage auto seulement. Ajouter au minimum un flux photo →
    pré-remplissage.
16. **Vraie carte des dépôts** (Foyer §5.1) : le PRD demande une carte ; l'app
    affiche une liste triée par distance.

### Incohérences à corriger (dette)

- `MandataireController::reappros`, la page web `Reappros.tsx` et les fixtures
  existent sans producteur de données réel (lié au manque #11).
- `POST /depots/{org}/propositions` : endpoint fonctionnel mais sans parcours
  réel côté dépôt (lié au manque #13).
- 4e événement de notification (proposition réappro/tournée) non branché (lié au
  manque #11).

### Hors périmètre v1 assumé (non comptés comme manques)

Recettes / temps de cuisson, paiement en ligne, écran de cuisine comme produit ;
envoi réel des notifications (abstrait derrière `CanalNotification`) ; agrégations
continues TimescaleDB ; compilation du firmware.

## 8. Conclusion

Yagaz v1 dispose d'un **socle complet, testé et sécurisé** couvrant les cinq
acteurs, avec une chaîne de mesure originale (poids → niveau → autonomie),
une boucle de commande de bout en bout, et une lecture régionale étanche. Le
travail restant pour une v1 « concept complet » se concentre sur le
**déclenchement automatique de la chaîne** (alerte → livreur/dépôt → proposition)
et sur trois compléments d'expérience (i18n, carte, tare par photo). Ces points
sont listés ci-dessus pour être priorisés ensemble, et non traités dans cette
session comme demandé.


# Rapport de phase — Phase 5 : Mandataire et distributeur

Date : 2026-09-08

## Objectif

Donner au mandataire sa vue consolidée et ses tournées (terrain + pilotage), au
distributeur sa lecture régionale agrégée, et poser le service de notifications.
Le tout sans jamais exposer de donnée individuelle de foyer au distributeur.

## Construit

### Contrat (`docs/11-contrat-api-mandataire-distributeur.md`)
Mandataire (dépôts consolidés, réappros, tournées), distributeur (agrégats par
zone), notifications, dashboards web.

### Backend (`yagaz-api`)
- **Mandataire** : vue consolidée des dépôts (stock plein/vide, tensions),
  réappros (commandes d'origine dépôt), **tournées** (`CycleTournee` : création/
  validation à partir des réappros, lignes dépôt/format avec pleines à déposer
  **et vides à récupérer** — logistique inversée, statut forward-only, dépôts et
  livreur vérifiés dans le périmètre).
- **Distributeur** : agrégats régionaux (`AgregationRegionale`) par **zone** et
  format, dans le temps — SQL portable (buckets en PHP), cloisonnés à la branche.
  **Aucune donnée individuelle de foyer** ne transparaît (test de non-fuite).
- **Notifications** : service `Notificateur` + interface `CanalNotification`
  (implémentation `CanalLog` par défaut, vrais providers branchables). Les
  `alertes` deviennent adressables (`commande_id`, `site_id`, `destinataire`).
  Événements branchés : seuil bas, proposition, statuts de commande.

### Dashboards web (`yagaz-web`, React 19)
- Auth web (Sanctum), `AppShell` avec sélecteur d'espace.
- **Mandataire** : dépôts consolidés (tensions), tournées (préparation/validation
  depuis les réappros, vides visibles à part entière), réappros.
- **Distributeur** : demande régionale (courbes recharts par zone/format,
  granularité jour/semaine/mois), tensions par zone, volumes — agrégats seulement.

### App mandataire mobile (`yagaz-app`)
- Espace mandataire ajouté au sélecteur : **tournée du jour** dépôt par dépôt
  (pleines à déposer / vides à récupérer, avancement du statut d'un arrêt en un
  geste), vue rapide des dépôts. Le pilotage riche reste sur le web.

## Sécurité (audit Opus + corrections)
Audit mené en Opus. **Verdict : l'étanchéité distributeur ↔ foyer tient** (le
point critique du cadrage, doc 04 §8) — aucun chemin de fuite, désagrégation par
paramètre impossible. Aucun finding critique/élevé. Corrigé :
- **Borne de 24 mois** sur la plage des agrégats (anti-DoS mémoire) + limite de
  sécurité sur les lignes chargées.
- **Throttle** sur les réglages d'alerte et **cible livreur restreinte** (fin de
  l'oracle d'énumération de téléphones).
- **Invariant anti-fuite** gravé dans le notificateur : une alerte adressée à un
  destinataire sans accès au site n'emporte pas les références foyer.
- Résiduel documenté (non-PII, accepté) : k=1 possible sur une quantité agrégée
  (une zone/jour à une seule commande) — pas d'identité révélée ; un seuil de
  suppression est une option ultérieure.

## Tests
- **131 tests, 442 assertions, tout vert** (dont phases précédentes).
- Mandataire (cloisonnement, réappros, tournées avec vides), distributeur
  (agrégats + non-fuite foyer + cloisonnement de branche + borne de dates),
  notifications (adressage, visibilité, invariant anti-fuite).
- CI verte (migrations Timescale + suite complète + build web + typecheck app).

## Limites / dette
- Notification « proposition de réappro/tournée » (un des quatre événements
  listés) non encore branchée ; les trois autres le sont. À câbler sur
  `CycleTournee` si souhaité.
- Envoi réel des notifications (FCM/APNs, SMS, WhatsApp) : abstrait derrière
  `CanalNotification`, implémentation `CanalLog` en v1 ; les providers se
  branchent sans changer le code appelant.
- Agrégats via requêtes à la volée (portables) ; les agrégations continues
  TimescaleDB (optimisation à l'échelle) pourront les remplacer sans changer le
  contrat.
- Détail d'une tournée (`GET /tournees/{uuid}`) non exposé : le web/mobile la
  retrouve dans la liste — endpoint dédié à ajouter si besoin.

# Contrat d'API v1 — mandataire, distributeur, notifications (Phase 5)

*Extension du contrat (`docs/09`, `docs/10`). Mêmes conventions : Bearer Sanctum,
uuid exposés, enveloppe `data`, 404 hors périmètre, écritures via FormRequest,
cloisonnement par organisation avec accès descendant dans la hiérarchie.*

## 1. Mandataire — vue consolidée et tournées

Accès : membre `mandataire` de l'organisation. Périmètre = ses dépôts (enfants).

| Méthode | Route | Notes |
|---|---|---|
| GET | `/api/mandataires/{orgUuid}/depots` | Vue consolidée de tous ses dépôts : stock plein/vide par format, tensions (stock bas, vides accumulés), dernière activité. |
| GET | `/api/mandataires/{orgUuid}/reappros` | Demandes de réapprovisionnement préparées automatiquement à partir de l'état des stocks des dépôts (dépôt en tension → proposition de réappro). |
| GET | `/api/mandataires/{orgUuid}/tournees` | Tournées (proposées / validées / en cours / terminées). |
| POST | `/api/mandataires/{orgUuid}/tournees` | `{ date, livreur_user_id?, lignes: [{ depot_uuid, format_id, pleines, vides_a_recuperer }] }` → crée/valide une tournée à partir des réappros. La plateforme **propose**, le mandataire **valide/ajuste**. |
| PATCH | `/api/tournees/{uuid}` | `{ statut?, livreur_user_id?, lignes? }` — ajuster/valider. |

Réappro (dépôt → mandataire) : une commande d'`origine = depot`, `cible_org` =
le mandataire. Le dépôt confirme/ajuste (réutilise `POST /commandes/{uuid}/reponse`
côté dépôt ou un endpoint dédié), le mandataire la voit dans `reappros`.

## 2. Distributeur — demande régionale agrégée

Accès : membre `distributeur`. **Jamais** de donnée individuelle de foyer — que
des agrégats (doc 04 §8, doc 07 §9). Les agrégats portent une clé de **zone**,
jamais une clé de foyer.

| Méthode | Route | Notes |
|---|---|---|
| GET | `/api/distributeurs/{orgUuid}/demande?depuis=&jusqua=&pas=jour\|semaine\|mois` | Demande agrégée : volumes (commandes livrées / réappros) par **zone** et par **format**, dans le temps. |
| GET | `/api/distributeurs/{orgUuid}/zones` | Tensions par zone (dépôts en rupture, vides accumulés) — carte de chaleur. |
| GET | `/api/distributeurs/{orgUuid}/volumes?...` | Évolution des volumes distribués, comparaison entre zones, saisonnalité. |

Implémentation : agrégation SQL standard (GROUP BY zone/format/période) sur
`commandes`/`livraisons`/`mouvements_stock`, portable SQLite/PostgreSQL. Les
agrégations continues TimescaleDB (optimisation) pourront remplacer les requêtes
à la volée quand le volume l'exigera — sans changer le contrat.

## 3. Notifications

Service unique adressant push / SMS / WhatsApp selon le canal disponible et les
préférences de l'utilisateur (doc 04 §7). En v1, l'**envoi réel** est abstrait
derrière une interface `CanalNotification` ; l'implémentation par défaut
journalise (log) — les vrais providers (FCM/APNs, SMS, WhatsApp) se branchent
sans changer le code appelant.

Événements qui notifient :
- **Seuil bas** (foyer) — déjà généré par l'ingestion (Phase 2).
- **Proposition de livraison** (dépôt/livreur → foyer).
- **Statut de commande** (préparée, en livraison, livrée).
- **Proposition de réappro / tournée** (mandataire ↔ dépôt).

Liaison : les `alertes` gagnent des références optionnelles (`commande_id`,
`site_id`, `user_id` destinataire) pour être adressables et lisibles par le bon
acteur. Sobriété : une notification par événement utile, pas de répétition.

| Méthode | Route | Notes |
|---|---|---|
| GET | `/api/notifications` | Notifications de l'utilisateur courant (tous rôles), filtre `?statut`. |
| PATCH | `/api/notifications/{id}` | `{ statut: "vue" }`. |

(Complète `GET /api/alertes` du foyer ; une notification est une alerte adressée.)

## 4. Dashboards web (React 19, `yagaz-web`)

- **Mandataire** : vue consolidée des dépôts (tableau + tensions), préparation et
  validation des tournées (la plateforme propose, il valide), logistique inversée
  (vides comme signal de demande) visible à part entière.
- **Distributeur** : demande régionale (cartes + courbes), tensions par zone,
  évolution des volumes. Outil de décision, pas d'opérationnel quotidien.
- Le mandataire a aussi une entrée **mobile** (tournée du jour) dans `yagaz-app`.

Les dashboards consomment le même contrat d'API que les apps ; l'auth web réutilise
Sanctum (jeton stocké côté client). Palette corail/blanc, grands tableaux et
graphiques épurés (référence design).

## 5. Cloisonnement (rappel)

- Mandataire : voit et agit sur ses dépôts (hiérarchie descendante), pas ceux
  d'un autre mandataire.
- Distributeur : agrégats de sa branche uniquement, jamais de foyer individuel.
- Toute ressource hors périmètre → 404.

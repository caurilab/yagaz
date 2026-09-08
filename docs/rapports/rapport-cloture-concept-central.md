# Rapport de clôture — Concept central : la mesure déclenche la logistique

Date : 2026-09-08
Dépôt : `github.com/caurilab/yagaz` (branche `main`, CI verte)

Cette session avait une mission unique : **fermer la boucle du déclenchement
automatique**, pour que Yagaz cesse d'être un gestionnaire de commandes et
devienne le produit du cadrage — *la mesure déclenche la logistique*, la
plateforme propose, l'humain confirme.

## 1. Ce qui a été branché, maillon par maillon

### A. Alerte de niveau bas → notification du livreur habituel
Quand l'ingestion fait passer une bouteille **active** sous le seuil bas, en plus
du foyer, le **livreur habituel** du site est notifié (s'il est désigné). La
notification est un **rappel minimal** : `contexte = {site_nom, zone, format}` +
une référence opaque (HMAC du site_id) pour l'anti-spam — **aucun** identifiant
foyer, niveau, autonomie, historique ni contact.

### B. File des foyers en tension côté dépôt
`GET /depots/{org}/foyers-en-tension` : le dépôt voit les foyers **de sa zone de
desserte** (déjà clients, ou rattachés par un livreur habituel membre du dépôt)
dont une bouteille active est en tension. Actionnable : la proposition dépôt →
foyer part de cette file (plus de saisie d'UUID à l'aveugle). Distance bucketisée.

### C. Proposition par le livreur habituel
`GET /livreur/foyers-en-tension` : file actionnable des foyers **habituels** du
livreur, **en tension** (site_uuid + nom + zone + format, rien de plus). Depuis
cette file, `POST /livreur/propositions` crée une commande `proposee` ; le foyer
confirme. La **désignation** du livreur habituel se fait par le foyer,
propriétaire du site (`POST /sites/{uuid}/livreur-habituel`, cible = vrai livreur).

### D. Réappro dépôt → mandataire
Quand le stock **plein** d'un dépôt passe sous son seuil, la plateforme
**prépare** automatiquement un réappro (commande `origine=depot`, `proposee`,
vers le mandataire parent, quantité calculée, idempotente). Le **dépôt confirme/
ajuste** (`confirmer-reappro` → `confirmee`) ; le **mandataire** le voit dans
`GET /mandataires/{org}/reappros` (données réelles désormais) et le traite via ses
tournées. Le 4e événement de notification (`reappro_prepare` / `reappro_confirme`)
est branché.

## 2. Décisions d'autorisation et d'étanchéité (ADR)

- **ADR 0008 — Étanchéité du livreur tiers** : la notification est minimale et
  sans identifiant ; la file actionnable du livreur n'expose l'identité (uuid,
  nom, zone, format) que de **ses** foyers habituels **en tension** — jamais
  niveau, autonomie, historique, autres bouteilles/sites, ni contact. L'adresse
  et la quantité ne viennent qu'avec la mission, après affectation humaine.
- **ADR 0009 — Déclenchement automatique** : à chaque maillon, la plateforme
  **prépare et propose**, l'humain **confirme**. Aucun automatisme n'engage
  (aucune commande ferme, livraison ou décrément de stock sans action humaine).

## 3. Étanchéité vérifiée (fil rouge sécurité)

Chaque transmission d'information vers un acteur hors du périmètre du foyer est
bornée au minimum et **vérifiée par un test d'étanchéité dédié**, sur le modèle
de l'étanchéité distributeur :
- notification livreur (contexte minimal, sans identifiant) ;
- file livreur (identité de ses foyers habituels en tension seulement) ;
- file dépôt (zone de desserte, distance bucketisée, pas de niveau exact).
Audit Opus de la boucle : **verdict positif** — l'étanchéité tient, « propose,
l'humain confirme » respecté. Findings corrigés (désignation livreur câblée,
index d'idempotence réappro, dédup homonyme, distance bucketisée).

## 4. Tests

- **168 tests** backend, verts (dont les tests d'étanchéité du livreur tiers et
  la boucle réappro). App `tsc` verte, web build vert. CI verte à chaque push.
- Suites clés : `AlerteSeuilBasLivreurHabituelTest`, `DepotFoyersEnTensionApiTest`,
  `LivreurPropositionApiTest`, `LivreurFoyersEnTensionApiTest`, `ReapproApiTest`,
  `SiteApiTest` (désignation).

## 5. Dette résorbée

- `GET /mandataires/{org}/reappros` renvoie du **réel** (producteur = maillon D) ;
  `Reappros.tsx` (web) branché sur le vrai endpoint (fin du tout-fixtures).
- `POST /depots/{org}/propositions` doté d'un **parcours réel** (file dépôt).
- **4e événement de notification** (réappro) branché.
- Note obsolète du client d'API nettoyée.

## 6. La chaîne se déclenche-t-elle toute seule ? (parcours par parcours)

- **Foyer** : une bouteille active qui descend sous le seuil → alerte foyer +
  (si désigné) notification livreur habituel. ✅
- **Livreur habituel** : reçoit le rappel, ouvre sa file de tension, **propose**
  en un geste ; le foyer confirme. ✅
- **Dépôt** : voit ses foyers en tension, **propose** ; son stock plein qui baisse
  déclenche un **réappro préparé** qu'il confirme. ✅
- **Mandataire** : voit les réappros confirmés (réels) et les intègre à ses
  tournées. ✅
À chaque étape, **l'humain confirme** ; rien n'est livré ni commandé fermement
sans un clic.

## 7. Zones grises / recommandations (listées, non traitées)

- **Écran foyer de désignation** du livreur habituel par site : l'endpoint existe
  (`POST /sites/{uuid}/livreur-habituel`) ; l'app foyer a un réglage de
  préférence de compte (`users.livreur_habituel_user_id`) mais pas encore l'écran
  de désignation **par site**. À ajouter pour que le foyer active la boucle A/C
  depuis l'app. (Petit chantier UI.)
- **Distance côté file livreur** : non exposée (pas d'origine unique) ; si un tri
  par proximité est souhaité, définir une origine (position du livreur) — à cadrer.
- **Zone de desserte** : en v1, « déjà client OU livreur habituel rattaché ». Une
  vraie relation dépôt ↔ zone géographique affinerait la file dépôt (ADR 0009 B).
- Reste valable : envoi réel des notifications (push/SMS/WhatsApp), TLS MQTT,
  prototype physique — voir `rapport-final.md` §6.

La phrase qui définit le produit est désormais vraie dans le code : **la mesure
déclenche la logistique, et l'humain garde la main.**

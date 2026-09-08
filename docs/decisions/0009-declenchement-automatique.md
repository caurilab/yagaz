# ADR 0009 — Déclenchement automatique de la chaîne

Date : 2026-09-08
Statut : accepté

## Contexte

Le cœur de Yagaz : *la mesure déclenche la logistique*. La v1 avait la chaîne
mais pas son déclenchement automatique. Le PRD §7 fixe le principe :
**la plateforme prépare et propose, l'humain confirme.** Cet ADR fixe, à chaque
maillon, ce qui est automatique et ce qui demande un clic.

## Décision

### A. Seuil bas → livreur habituel
L'ingestion, quand une bouteille **active** franchit `seuil_bas_pct` à la baisse
(anti-spam existant), notifie — en plus du foyer — le **livreur habituel** du
site s'il est défini. Information minimale (ADR 0008). Automatique ; aucune
commande n'est créée à ce stade.

### B. File des foyers en tension côté dépôt
Un dépôt dispose d'une **file** des foyers **de sa zone de desserte** dont une
bouteille active est en tension (sous le seuil), présentée de façon actionnable.
« Zone de desserte » en v1 = les foyers **déjà clients du dépôt** (au moins une
commande passée vers ce dépôt) **ou** rattachés par un livreur habituel membre du
dépôt, dont la zone du site correspond. Défaut retenu : **déjà client OU livreur
habituel rattaché**, pour rester étanche (le dépôt ne découvre pas des foyers
inconnus). Ajustable quand une vraie relation dépôt↔zone existera.

### C. Proposition
Depuis la file (dépôt) ou la notification (livreur habituel), l'acteur **propose**
une livraison. Le droit du livreur est ouvert **uniquement** pour ses foyers
habituels en tension. La proposition crée une commande `proposee` ; **le foyer
confirme** (oui/non) — mécanisme existant. Rien n'est livré sans confirmation.

### D. Stock de dépôt en tension → réappro proposé au mandataire
Quand le stock **plein** d'un dépôt passe sous `seuil_plein_bas` (ou que les
vides s'accumulent), la plateforme **prépare** une proposition de réappro : une
commande `origine=depot` **en état `proposee`** ciblant le mandataire parent,
quantité calculée pour revenir au-dessus du seuil. Le **dépôt confirme/ajuste**
(passe la commande en `confirmee`) avant qu'elle n'apparaisse comme réappro ferme
au mandataire. Le mandataire la voit dans `GET /mandataires/{org}/reappros` et la
traite via ses tournées. Le 4e événement de notification (proposition de réappro)
est émis vers le dépôt (préparé) puis le mandataire (confirmé).

### Qui déclenche la préparation automatique ?
La préparation (A déclenche à l'ingestion ; D déclenche au changement de stock)
est **idempotente** et **anti-spam** : pas de doublon tant qu'une proposition/
alerte non résolue existe pour la même cible. La production de réappro est
recalculée à chaque mouvement de stock pertinent, sans créer de doublon.

## Conséquences

- Aucun automatisme ne **commande** à la place de l'humain : tout ce qui engage
  (commande ferme, livraison) passe par une confirmation humaine.
- Les propositions et réappros sont des **commandes** réutilisant le cycle et les
  statuts existants (`proposee → confirmee → …`), sans nouveau modèle.
- Les frontières d'accès nouvelles (livreur habituel, file dépôt) sont tracées et
  testées (ADR 0008).

# ADR 0008 — Étanchéité du livreur tiers

Date : 2026-09-08
Statut : accepté

## Contexte

Le déclenchement automatique de la chaîne (concept central) fait circuler une
information de foyer vers un acteur qui n'était pas dans son périmètre : le
**livreur habituel**, désigné par le foyer (`livreur_habituel_user_id`). C'est le
risque de sécurité central de cette phase (cf. prompt de reprise). Le principe
directeur : à chaque transmission, ne transmettre que le **strict minimum
nécessaire pour agir**, sur le modèle de l'étanchéité distributeur déjà en place.

Le foyer qui **désigne** un livreur habituel consent à ce que ce livreur reçoive
de quoi le livrer. Ce consentement ne s'étend pas à sa consommation ni à ses
autres bouteilles/sites.

## Décision

Quand une bouteille **active** passe sous le seuil bas et qu'un livreur habituel
est défini sur le site, ce livreur reçoit une notification qui expose uniquement :

- le **nom d'affichage du site** et sa **zone** (pas l'adresse précise à ce stade) ;
- le **format** de la bouteille concernée (pour apporter la bonne recharge) ;
- le fait qu'une recharge est opportune (déclencheur), **sans** le niveau exact,
  l'autonomie, l'historique de consommation, ni la liste des autres
  bouteilles/sites du foyer.

Le livreur habituel peut alors **proposer** une livraison (maillon C). L'adresse
précise et la quantité ne lui sont fournies qu'**une fois la proposition acceptée
et la livraison affectée**, via la ressource de mission déjà en place
(`LivraisonMissionResource` : site nom/adresse + format + quantité), qui
**n'expose déjà pas** le téléphone ni l'identité étendue du foyer.

Un livreur ne reçoit ces informations que pour les foyers **dont il est le
livreur habituel** et qui sont **effectivement en tension** — jamais pour un
foyer quelconque.

## Précision (maillon C) — identité des foyers habituels en tension

La **notification** de seuil bas reste un simple rappel minimal (`{site_nom, zone,
format}` + `ref` opaque, sans uuid). Mais pour **proposer** une livraison, le
livreur doit identifier le foyer. On distingue donc deux niveaux :

- **Notification (nudge)** : minimale, sans identifiant.
- **File actionnable du livreur** : `GET /livreur/foyers-en-tension` renvoie, pour
  les sites dont l'utilisateur est le **livreur habituel désigné** ET qui sont
  **en tension**, l'identité nécessaire à l'action : `site_uuid`, `nom`, `zone`,
  `format`. Rien de plus — ni niveau, ni autonomie, ni historique, ni autres
  bouteilles/sites, ni contact.

Justification : le foyer a **explicitement désigné** ce livreur (via
`POST /sites/{uuid}/livreur-habituel`, propriétaire only) ; ce consentement couvre
le fait que ce livreur sache lequel de ses foyers habituels a besoin d'une
recharge. Le périmètre reste strict (ses foyers habituels, en tension seulement).
L'adresse précise et le contact ne viennent qu'avec la mission, après affectation
humaine (`LivraisonMissionResource`, sans téléphone/identité étendue).

## Conséquences

- Le `Notificateur` applique déjà l'invariant « pas de référence site/bouteille
  vers un destinataire sans accès » (ADR 0007 / audit Phase 5). Pour le livreur
  habituel, on transmet donc un **sous-ensemble dédié** (nom/zone/format), pas les
  références foyer complètes : une projection minimale spécifique, pas l'alerte
  foyer telle quelle.
- Un **test d'étanchéité dédié** vérifie qu'une notification/So proposition côté
  livreur tiers ne laisse jamais fuiter niveau, autonomie, historique, autres
  bouteilles/sites, ni contact — sur le modèle du test de non-fuite distributeur.
- Le droit de proposer est ouvert au livreur **uniquement** pour ses foyers
  habituels en tension (autorisation tracée, testée) — voir ADR 0009.

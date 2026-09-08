# Rapport de phase — Phase 4 : Dépôt et boucle de commande

Date : 2026-09-08

## Objectif

Fermer la boucle foyer → dépôt → livraison : commande de recharge par le foyer,
traitement au dépôt (stock, file de commandes, préparation, affectation),
exécution par un livreur, avec propagation des statuts jusqu'au foyer.

## Construit

### Contrat (`docs/10-contrat-api-depot-commande.md`)
Cycle de vie de la commande et de la livraison, routes, règles de stock et de
propagation, écrans par rôle.

### Backend (`yagaz-api`)
- **Machine à états centralisée** dans des services transactionnels :
  `CycleCommande` (créer depuis foyer, proposer, répondre, préparer, affecter)
  et `CycleLivraison` (transitions ordonnées, mouvements de stock, propagation
  commande ↔ livraison). Transitions illégales rejetées côté serveur.
- Endpoints : rôles ; commandes (créer/lister/détail/réponse/préparer/livraison) ;
  dépôt (stocks, ajustement tracé, file de commandes, livreurs, propositions) ;
  livreur (missions, statut). Commission enregistrée à la création (non prélevée,
  ADR 0004).
- Stock : décrément à la préparation (refus si insuffisant), incrément des vides
  à la récupération, chaque variation tracée en `mouvement_stock`.

### Apps (`yagaz-app`)
- **Sélection multi-rôle** (`mes-roles`) : espaces foyer / dépôt / livreur ; un
  foyer simple reste inchangé.
- **Dépôt** : stock (pleines/vides, tension), file des commandes (préparer,
  affecter à un livreur), propositions.
- **Livreur** : missions (gros boutons), transitions de statut en un geste.
- **Foyer** : suivi de commande et réponse oui/non à une proposition.

## Sécurité (audit Opus + corrections)
Audit de la boucle mené en Opus. Verdict : base saine (états centralisés,
écritures verrouillées ADR 0005, cloisonnement 404 cohérent, pas de fuite PII
vers le livreur, commission serveur) ; aucun finding critique/élevé. Corrigé :
- **`vides_recuperes` borné** à la quantité de la commande (un livreur ne peut
  plus gonfler le stock de vides d'un dépôt).
- **Races TOCTOU fermées** : re-vérification du statut sous `lockForUpdate` dans
  la transaction (préparation, affectation) + **index unique** sur
  `livraisons.commande_id` (une commande = au plus une livraison).
- **Propositions restreintes** aux sites déjà clients du dépôt (404 sinon,
  suppression de l'oracle d'existence).
- Bornes (`quantite`), `$fillable` explicite sur Commande/Livraison/Stock.

## Tests
- **103 tests, 350 assertions, tout vert.**
- Boucle complète (statuts + stock à chaque étape), cloisonnement
  dépôt/livreur/foyer, transitions illégales, stock insuffisant, affectation
  invalide, double préparation/affectation refusée, propositions scindées
  client/non-client, mass-assignment ignoré. CI verte.

## Limites / dette
- **Proposition dépôt → foyer** : l'idée « le dépôt voit un niveau bas et
  propose » suppose une visibilité dépôt ↔ foyer que le cloisonnement v1 ne donne
  pas (un dépôt ne voit pas les niveaux des foyers). Retenu en v1 : le dépôt ne
  peut proposer qu'à un foyer **déjà client**. Une vraie relation dépôt ↔ zone /
  foyers desservis (et le déclenchement par le livreur habituel au seuil bas)
  est à concevoir — lié aux notifications.
- **Notifications** : les propositions et changements de statut devraient
  notifier le foyer ; la table `alertes` n'a pas encore de lien
  `commande_id`/`site_id` et l'envoi multi-canal (push/SMS/WhatsApp) n'est pas
  branché. À faire en Phase 5 (service de notification), avec la migration de
  liaison nécessaire.
- Réappro dépôt → mandataire : Phase 5.

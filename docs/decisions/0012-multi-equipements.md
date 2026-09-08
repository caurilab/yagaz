# ADR 0012 — Multi-équipements par compte et gating

Date : 2026-09-08
Statut : accepté

## Contexte

Un compte doit pouvoir posséder et connecter **plusieurs équipements** (autant
que voulu), de **trois types** :
- **balance** : pèse-bouteille sous la bouteille → niveau de gaz (le `plateau`
  ESP32 existant) ;
- **temperature** : capteur de température de cuisine (ADR 0011) ;
- **ecran** : écran de cuisine (afficheur du niveau/autonomie).

Cas d'usage : un foyer avec plusieurs bouteilles/sites, ou une personne qui
équipe et paie pour d'autres. La gestion doit vivre dans **Paramètres >
Matériels**, avec ajout par **code/lien d'acquisition** et affectation à un site.
Tant qu'un type d'équipement n'est pas connecté, la donnée correspondante (niveau
de gaz / température) est **indisponible** et l'app le montre grisé + un **lien
d'acquisition**.

## Décision

### Registre unifié `equipements`
Table `equipements` (registre visible par l'utilisateur) : `uuid`, `type`
(`balance|temperature|ecran`), `reference` (unique, le code d'acquisition gravé),
`site_id` (nullable, affectation), `statut` (`a_connecter|actif|hors_service`),
`secret_hash` (nullable, auth appareil), `dernier_vu_at`, timestamps.

Un compte peut en enregistrer autant qu'il veut ; chaque équipement s'affecte à
un site auquel l'utilisateur a accès.

### Lien avec l'ingestion existante
- L'ingestion du **poids** reste portée par `plateaux`, et de la **température**
  par le topic `.../temperature` (ADR 0003/0011). Le registre `equipements` est
  la couche **gestion/gating** côté utilisateur.
- Un équipement `balance`/`temperature` dont la `reference` correspond à l'`uid`
  d'un `plateau` provisionné est **relié** (l'app voit alors les données). La
  réconciliation fine plateau↔équipement (fusion des deux tables) est un
  chantier ultérieur ; en v1 on garde les deux et on relie par référence.

### Gating (disponibilité des données)
Chaque site expose des **capacités** dérivées de ses équipements `actif` :
`a_balance`, `a_temperature`, `a_ecran`. L'app :
- affiche le **niveau de gaz** seulement si `a_balance` (sinon grisé + « Acquérir
  une balance ») ;
- affiche la **température / cuisson** seulement si `a_temperature` (sinon grisé
  + « Acquérir le capteur de température ») ;
- l'**écran de cuisine** est un équipement optionnel (pas de donnée entrante à
  griser, mais listé dans Matériels).

### Endpoints
- `GET /api/equipements` — mes équipements (sur mes sites).
- `POST /api/equipements` — enregistrer `{ type, reference, site_uuid? }`.
- `PATCH /api/equipements/{uuid}` — affecter un site / changer le statut.
- `DELETE /api/equipements/{uuid}` — retirer.
- Capacités par site exposées dans `GET /api/sites` (ou `GET /api/sites/{uuid}`) :
  `a_balance`, `a_temperature`, `a_ecran`.

Cloisonnement : un équipement n'est visible/modifiable que par un utilisateur
ayant accès à son site (ou le créateur tant qu'il n'est pas affecté). 404 hors
périmètre.

## Conséquences

- Écran **Paramètres > Matériels** (app) : liste + ajout par code + affectation.
- Gating clair côté app avec liens d'acquisition.
- Le lien de provisioning `equipement.reference ↔ plateau.uid` sera précisé dans
  le runbook devops ; la fusion plateaux↔equipements est un TODO tracé.

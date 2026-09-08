# Prompt de reprise — Yagaz : fermer le concept central

> À coller dans Claude Code, à la racine du dépôt `yagaz/`.
> Session de reprise après le premier cycle de développement (voir
> `docs/rapports/rapport-final.md`). Objectif unique : brancher le
> déclenchement automatique de la chaîne. Rien d'autre ne passe devant.

---

## Où on en est

Le premier cycle a produit un socle complet, testé et sécurisé : les cinq
acteurs, la chaîne de mesure (poids → niveau → autonomie), la boucle de commande
de bout en bout, la lecture régionale étanche. 131 tests verts, sécurité auditée
à chaque phase.

Mais **le nerf central du produit n'est pas branché**. Toute la vision de Yagaz
tient dans une phrase : *la mesure déclenche la logistique*. Aujourd'hui la
chaîne fonctionne si quelqu'un commande à la main ; l'automatisme
« niveau bas détecté → la filière se met en mouvement » ne se referme pas. Tant
qu'il manque, Yagaz est un gestionnaire de commandes, pas le produit décrit dans
le cadrage.

Cette session ne fait que ça : **fermer la boucle du déclenchement automatique.**

## Avant de coder

Lis, dans cet ordre :

1. `docs/rapports/rapport-final.md` — l'état réel, en particulier la section 6,
   manques #10 à #13, et les incohérences de dette qui y sont liées.
2. `docs/03-prd.md` §7 (déclenchement de la chaîne) et §5 (parcours par acteur).
3. `docs/01-vision-et-concept.md` — pour rester fidèle à l'intention : la donnée
   déclenche, l'humain confirme.
4. `.ai/guidelines/` et `.claude/agents/` — conventions et rôles inchangés.

Le principe directeur du PRD reste la boussole : **la plateforme prépare et
propose, l'humain confirme.** À chaque maillon, l'automatisme fait remonter et
propose ; il ne commande pas tout seul à la place des gens.

## Comment tu travailles

Même méthode que le premier cycle, phase par phase, en autonomie :
construire → auditer la sécurité au fil de l'eau → tester (back-end d'abord) →
relire (revue-code + yagaz-reviewer) → intégrer (une branche par tâche) →
documenter (état du projet, ADR si structurant, rapport de phase).

Tu ne t'arrêtes que sur blocage dur ou en fin de phase. Sujet non tranché : tu
prends une valeur par défaut raisonnable, tu la notes en ADR, tu continues, tu
la signales.

## Ce qu'il faut fermer

Les quatre manques forment une seule boucle. Traite-les dans cet ordre, parce
que chacun débloque le suivant.

### A. Alerte de niveau bas → notification du livreur habituel (#10)

Le champ `livreur_habituel_user_id` est stocké mais jamais consommé par
l'ingestion. C'est le premier maillon de l'automatisme.

- Quand l'ingestion fait passer une bouteille **active** sous le seuil bas,
  déclencher une notification vers le livreur habituel du foyer, si défini.
- Respecter l'**invariant anti-fuite** déjà en place : un livreur tiers n'est pas
  un acteur du foyer. Décider le **minimum d'information** transmis — de quoi
  livrer (adresse du site, format de bouteille, contact), sans exposer
  l'historique de consommation ni les autres bouteilles/sites du foyer. Ce
  minimum est une décision de sécurité : documente-la en ADR et fais-la vérifier
  par un test d'étanchéité, comme pour le distributeur.
- Passer par le service de notification abstrait existant (`CanalNotification`) :
  tu câbles l'événement, pas l'envoi réel (qui reste `CanalLog` en v1).

### B. File des foyers en tension côté dépôt (#13)

La proposition dépôt → foyer existe mais est inutilisable : le dépôt doit saisir
l'UUID d'un site qu'il ne peut pas connaître. Il faut lui donner la matière.

- Fournir au dépôt une **file des foyers en tension** qu'il dessert : les sites
  dont une bouteille active est passée sous le seuil, présentés de façon
  actionnable (sans, là encore, exposer plus que le nécessaire logistique).
- Rendre la proposition dépôt → foyer utilisable depuis cette file, sans saisie
  d'UUID à l'aveugle.
- Étanchéité : le dépôt ne voit que les foyers de sa zone de desserte, et
  seulement ce qu'il faut pour livrer.

### C. Flux livreur « alerte → proposer la livraison » (#12)

L'espace livreur n'a aujourd'hui que ses missions. Il lui manque le maillon
amont : recevoir l'alerte et pouvoir proposer la livraison.

- Sur réception de la notification (maillon A), le livreur habituel peut
  **proposer une livraison** — l'équivalent, côté livreur, de ce que le gérant de
  dépôt peut déjà faire via `POST /depots/{org}/propositions`.
- Ouvrir proprement ce droit au livreur habituel du foyer concerné, sans élargir
  la permission au-delà (un livreur ne propose que pour les foyers dont il est le
  livreur habituel et qui sont en tension). Décision d'autorisation à tracer et à
  tester.
- L'humain confirme : la proposition reste une proposition, le foyer ou le
  circuit valide selon le modèle déjà en place.

### D. Boucle de réappro dépôt → mandataire (#11)

Le dernier maillon remonte la chaîne. Rien ne crée aujourd'hui de commande
`origine=depot` vers le mandataire ; `GET /mandataires/{org}/reappros` renvoie
toujours vide, et la page web `Reappros.tsx` et ses fixtures tournent à vide.

- **Préparer automatiquement le réappro** à partir des stocks de dépôt en
  tension : la plateforme calcule et propose, à partir de l'état réel des stocks.
- Écran **dépôt** de confirmation/ajustement du réappro proposé.
- Endpoint de réponse dépôt qui crée la commande `origine=depot` vers le
  mandataire.
- Câbler le **4e événement de notification** (proposition de réappro / tournée)
  aujourd'hui non branché.
- À la fin, `GET /mandataires/{org}/reappros` renvoie du réel et `Reappros.tsx`
  n'est plus alimenté par des fixtures : la dette liée (#11 des incohérences) se
  résorbe en même temps.

## Dette à résorber en chemin

Ces incohérences sont les symptômes des manques ci-dessus ; elles doivent
disparaître avec eux, pas rester en l'état :

- `MandataireController::reappros` + `Reappros.tsx` + fixtures → alimentés par le
  vrai producteur de données (maillon D).
- `POST /depots/{org}/propositions` → doté d'un parcours réel côté dépôt
  (maillon B).
- 4e événement de notification → branché (maillon D).

## Ce que tu NE fais PAS pendant la phase concept central

Tant que la boucle A→D n'est pas fermée et validée, tu restes concentré dessus
et tu laisses de côté ce qui suit. Certains de ces sujets reviennent ensuite
dans l'enchaînement v2 (voir plus bas) : ils sont écartés **pour cette phase**,
pas abandonnés.

- Le prototype physique : calibrage sur vraies bouteilles, compilation firmware,
  choix secteur/batterie (recommandations #4, #5, #6).
- Les compléments d'expérience : multilingue/i18n (#14), tare par photo (#15),
  vraie carte des dépôts (#16).
- Les bloquants prod : TLS MQTT, canaux de notification réels, pentest externe
  (#1, #3, #7).
- Tout ce qui est hors périmètre v1 (recettes, paiement en ligne, écran de
  cuisine, agrégations continues).

Si tu croises un de ces sujets, tu ne le traites pas : tu vérifies que ton
travail ne lui ferme pas la porte, et tu continues.

## Fil rouge sécurité de cette session

Le déclenchement automatique fait circuler de l'information vers des acteurs qui
n'étaient pas dans le périmètre du foyer — au premier chef le **livreur tiers**.
C'est le risque central de cette session. À chaque maillon qui transmet une
donnée de foyer vers l'extérieur, la question est la même : **quel est le strict
minimum nécessaire pour agir, et rien de plus ?** Chaque réponse est une décision
de sécurité, tracée en ADR et vérifiée par un test d'étanchéité dédié, sur le
modèle de l'étanchéité distributeur déjà en place.

## Ce que tu produis à la fin

1. Un rapport dans `docs/rapports/rapport-cloture-concept-central.md` : ce qui a
   été branché maillon par maillon (A→D), les décisions d'autorisation et
   d'étanchéité prises et leurs ADR, l'état des tests ajoutés (dont les tests
   d'étanchéité du livreur tiers), et la dette résorbée.
2. La confirmation, parcours par parcours, que la **chaîne se déclenche
   désormais toute seule** depuis l'alerte de niveau bas jusqu'à la proposition
   de recharge — avec, à chaque étape, l'humain qui confirme.
3. S'il reste des zones grises ou de nouvelles recommandations, tu les listes
   sans les traiter.

## Ensuite : enchaîner sur la v2

Une fois le concept central fermé **et validé** (boucle A→D branchée, tests
d'étanchéité verts, rapport de clôture produit), tu ne t'arrêtes pas là :
tu enchaînes sur la feuille de route de `docs/12-perspectives-v2.md`.

Ce document ordonne les quatre briques de la v2 par préalable. Tu les prends
**dans cet ordre**, en commençant par la première dont le préalable est levé :

1. **Paiement Mobile Money** — le point d'extension `PaymentProvider` est déjà
   isolé (ADR 0004). Préalable : la décision sur les moyens de paiement locaux
   (opérateurs visés, agrégateur ou intégration directe). Si elle n'est pas
   tranchée, tu prends une valeur par défaut raisonnable, tu la notes en ADR, tu
   la signales, et tu construis l'intégration derrière l'abstraction existante.
2. **Écran de cuisine comme produit** — préalable : retour terrain sur l'usage.
3. **Couche recettes** — préalable : mesure d'autonomie éprouvée (donc après le
   prototype physique).
4. **Donnée agrégée** — préalable : densité du parc. Le k-anonymat des agrégats
   (recommandation #8 du rapport final) cesse d'être optionnel ici : c'est un
   préalable dur de sécurité, à traiter avant toute exposition de la donnée.

Même méthode que pour le concept central : phase par phase, sécurité et tests à
chaque incrément, ADR pour les décisions structurantes, rapport de phase. Une
brique dont le préalable n'est pas levé dans cette session (prototype non
disponible, parc pas assez dense) : tu ne la forces pas, tu passes à la suivante
dont le préalable l'est, et tu notes clairement dans le rapport pourquoi elle
attend.

Chaque brique v2 mise en chantier suit son propre cadrage et, si elle engage
l'architecture, son ADR — comme le rappelle la fin de `12-perspectives-v2.md`.

## Rappel de posture

Une seule mission cette fois : rendre vraie la phrase qui définit le produit —
la mesure déclenche la logistique. Tu construis vite, tu sécurises chaque
transmission de donnée, tu testes l'étanchéité à chaque maillon, tu restes
fidèle au principe « la plateforme propose, l'humain confirme ». Quand tu
hésites, le PRD §7 tranche ; s'il ne tranche pas, prends la décision la plus
simple qui n'expose que le nécessaire, note-la, et avance.

# Git — conventions

## Un seul dépôt

Yagaz est un monorepo : un seul `.git`, à la racine `yagaz/`. Les sous-projets
(`yagaz-api`, `yagaz-web`, `yagaz-app`, `yagaz-firmware`) sont des dossiers du
même dépôt, pas des dépôts séparés.

## Branches

Une branche par tâche, jamais par projet. Courte durée de vie, mergée vite.

Préfixe par domaine touché :

```
api/        yagaz-api
web/        yagaz-web
app/        yagaz-app
firmware/   yagaz-firmware
db/         schéma et migrations
docs/       documentation et ADR
```

Exemples :

```
api/feat-ingestion-mesures
app/feat-ecran-niveau-bouteille
web/fix-auth-token
firmware/feat-lecture-hx711
db/feat-tables-commandes
docs/adr-choix-mqtt
```

## Commits

Messages clairs, à l'impératif, qui disent quoi et pourquoi plutôt que comment.
Un commit = une intention cohérente.

## Éviter les collisions

Le monorepo ne crée pas les collisions. Elles viennent d'un endroit précis :
deux tâches qui touchent la **même frontière** au même moment. Dans Yagaz, cette
frontière est presque toujours le **contrat d'API** entre `yagaz-api` et ses
clients.

Règles :

1. Chaque agent reste dans son dossier. Backend dans `yagaz-api/`, mobile dans
   `yagaz-app/`, etc. Deux agents dans deux dossiers différents ne collisionnent
   pas, même sur la même base.
2. Tout changement du contrat d'API passe par l'architecte et est signalé au
   committeur, qui ordonne les merges concernés.
3. On rebase sur la branche principale avant de merger, pour intégrer ce qui a
   bougé entre-temps.
4. On ne laisse pas une branche diverger des jours : plus elle vit longtemps,
   plus le merge fait mal.

## Rôles

- **committeur** : sérialise les merges, garde l'historique propre.
- **revue-code** : relit avant merge sur les périmètres sensibles.
- **architecte** : arbitre les changements de contrat d'API.

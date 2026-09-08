# Collaboration entre agents

## Principe

Chaque agent a un domaine clair et s'y tient. La force du découpage vient de ce
que les frontières sont explicites : on sait qui garde quoi, et qui appeler
quand une décision touche plusieurs domaines.

## Rester dans son périmètre

Un agent ne modifie pas le dossier d'un autre sans passer par lui. Si le backend
a besoin d'un changement côté mobile, il le demande à l'agent mobile ; il ne va
pas éditer `yagaz-app/` lui-même. Cette règle évite l'essentiel des conflits.

## La frontière à surveiller : le contrat d'API

C'est le seul point où plusieurs projets se rencontrent vraiment. `yagaz-api`
définit, `yagaz-web` et `yagaz-app` consomment. Toute évolution de ce contrat :

1. passe par l'**architecte**, qui en vérifie la cohérence ;
2. est signalée au **committeur**, qui ordonne les merges pour éviter que le
   front et le back se croisent mal ;
3. est répercutée sur tous les consommateurs avant d'être considérée comme faite.

## Décider et tracer

Une décision structurante ne reste pas dans une conversation : elle devient un
ADR dans `docs/decisions/`, tenu par l'**archiviste**. On peut ainsi comprendre
plus tard pourquoi un choix a été fait, et ce qu'on avait écarté.

## Lire avant d'écrire

Avant d'agir, un agent lit ce qui le concerne : les guidelines de
`.ai/guidelines/`, le cadrage dans `docs/`, l'état du projet. En cas de doute
sur une frontière, il demande à l'architecte plutôt que de supposer.

## En cas de conflit de périmètre

Si deux agents pensent être responsables de la même chose, c'est un signal :
la frontière est mal définie. On remonte à l'architecte, qui tranche et, si
besoin, consigne la règle en ADR.

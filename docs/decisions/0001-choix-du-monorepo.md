# ADR 0001 — Choix du monorepo

Date : 2026-09-08
Statut : accepté

## Contexte

Yagaz regroupe plusieurs projets : une API (`yagaz-api`), des dashboards web
(`yagaz-web`), une application mobile (`yagaz-app`) et, à venir, un firmware
(`yagaz-firmware`). Ils partagent un même contrat d'API et des concepts métier
communs. Il fallait décider comment versionner l'ensemble : un seul dépôt, un
dépôt par projet, ou un méta-dépôt avec sous-modules.

Une inquiétude a été exprimée : le risque de collisions entre travaux, surtout
avec plusieurs agents travaillant en parallèle.

## Décision

Un **monorepo** : un seul dépôt Git à la racine `yagaz/`. Les sous-projets sont
des dossiers du même dépôt, pas des dépôts séparés.

## Alternatives écartées

- **Multi-repos indépendants** : un dépôt par projet. Écarté parce qu'il
  complique la cohérence du contrat d'API partagé et disperse l'historique d'un
  produit qui avance de front sur plusieurs tableaux.
- **Méta-repo + sous-modules Git** : écarté pour sa lourdeur au quotidien
  (synchronisation des sous-modules, friction sur les opérations courantes),
  sans bénéfice clair à ce stade.

## Conséquences

- Un seul historique, une seule source de vérité pour tout le produit.
- Les collisions ne viennent pas du dépôt unique mais des frontières partagées,
  au premier chef le contrat d'API. Elles se maîtrisent par la discipline de
  branches (une branche par tâche, préfixée par domaine) et par les rôles de
  committeur, revue de code et architecte, pas par la séparation des dépôts.
- Voir `.ai/guidelines/git.md` pour la convention de branches et la pratique
  anti-collision.

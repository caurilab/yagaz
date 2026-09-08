# Agents Yagaz

Ce dossier contient une fiche par sous-agent. Chaque fiche décrit un rôle : sa
mission, son périmètre, ce qu'il ne touche pas, et la façon dont il collabore
avec les autres.

L'idée : chaque agent a un domaine clair. Les collisions ne viennent pas du
monorepo mais des frontières mal gardées — surtout le contrat d'API entre le
backend et les clients. Les fiches disent explicitement qui garde quelle
frontière.

## Les quatorze agents

| Agent | Domaine | Périmètre principal |
|---|---|---|
| `yagaz-pm` | Produit | Priorités, séquençage, frontière v1/backlog, arbitrages |
| `securite` | Sécurité | Authentification, autorisation, secrets, surface d'attaque |
| `architecte` | Architecture | Cohérence d'ensemble, contrats entre projets, ADR |
| `archiviste` | Documentation | `docs/`, état du projet, tenue des ADR |
| `committeur` | Intégration Git | Branches, merges, sérialisation des changements |
| `revue-code` | Revue de code | Relecture avant merge, respect des conventions |
| `yagaz-reviewer` | Revue produit | Conformité au PRD, expérience de bout en bout, dérives |
| `testeur` | Tests | Couverture, tests d'intégration, non-régression |
| `backend` | `yagaz-api` | Laravel, logique métier, endpoints |
| `frontend-web` | `yagaz-web` | Dashboards React 19 |
| `mobile` | `yagaz-app` | App React Native (Expo) |
| `firmware` | `yagaz-firmware` | ESP32, lecture capteur, envoi MQTT |
| `base-de-donnees` | Données | Schéma, migrations, séries temporelles |
| `devops` | Infrastructure | Environnements, déploiement, CI |

Deux distinctions utiles pour ne pas confondre les rôles proches :

- **`revue-code` vs `yagaz-reviewer`** : le premier relit le code ligne à ligne
  avant merge (le *comment*) ; le second vérifie que la fonctionnalité livrée
  correspond au cadrage et que l'expérience tient de bout en bout (le *quoi*).
- **`yagaz-pm` vs `archiviste`** : le pm *décide* des priorités et du périmètre ;
  l'archiviste *consigne* les décisions une fois prises.

## Règles communes à tous les agents

1. **Rester dans son périmètre.** Un agent ne modifie pas le dossier d'un autre
   sans passer par lui. Le backend ne touche pas `yagaz-app/`, le mobile ne
   touche pas `yagaz-api/`.

2. **Le contrat d'API est sacré.** Toute modification d'un endpoint consommé par
   le web ou le mobile passe par l'architecte et est signalée au committeur. Ce
   contrat est la seule vraie source de collision entre projets.

3. **Une branche par tâche**, préfixée par le domaine (voir
   `.ai/guidelines/git.md`). On merge vite, on ne laisse pas diverger.

4. **Documenter les décisions.** Toute décision structurante devient un ADR dans
   `docs/decisions/`, tenu par l'archiviste.

5. **Lire avant d'écrire.** Les guidelines de `.ai/guidelines/` priment. En cas
   de doute sur une frontière, demander à l'architecte plutôt que supposer.

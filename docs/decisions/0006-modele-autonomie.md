# ADR 0006 — Modèle de calcul de l'autonomie

Date : 2026-09-08
Statut : accepté (valeur par défaut, à raffiner avec les données réelles)

## Contexte

L'autonomie « en heures de flamme » est l'information centrale côté foyer
(UX §7). Le cadrage dit : autonomie déduite du gaz restant et du **débit observé**
au fil du temps. Mais au démarrage d'une bouteille, aucun débit n'est encore
observé, et distinguer précisément les périodes « flamme allumée » du bruit
demande de l'historique. Il faut une formule qui donne tout de suite un nombre
crédible et se raffine.

## Décision

Autonomie v1 :

```
autonomie_heures = gaz_restant_g / debit_flamme_g_par_h
```

- `debit_flamme_g_par_h` = **débit de consommation quand la flamme est allumée**.
- Valeur **nominale de départ : 150 g/h** (ordre de grandeur d'un brûleur
  domestique à feu moyen). Utilisée tant que l'historique est insuffisant.
- **Raffinement** : à mesure que l'historique s'accumule, on estime le débit réel
  en isolant les pentes de décroissance soutenues du poids (flamme allumée) sur
  une fenêtre glissante (14 j), en excluant les rechargements (sauts positifs) et
  les périodes inactives. Le débit observé remplace alors le nominal.
- L'autonomie est présentée comme une **estimation** tant que `tare_fiable` est
  faux ou que le débit repose encore sur le nominal.

## Pourquoi ce choix

- Donne immédiatement une réponse à « combien de temps il me reste » sans
  attendre des semaines de données.
- Se raffine automatiquement, sans intervention de l'utilisateur.
- Le débit « flamme allumée » (et non le débit moyen calendaire) correspond à la
  question réelle du foyer : combien d'**heures de cuisson** il reste.

## Alternatives écartées

- **Débit moyen calendaire** (gaz consommé / temps écoulé, jours inclus) :
  donnerait une « autonomie en jours » dépendante du rythme de vie, moins
  parlante que des heures de cuisson, et trompeuse après une période sans usage.
- **Pourcentage seul** : le cadrage insiste pour que les heures priment sur le %.

## Conséquences

- Constante `debit_flamme_nominal = 150 g/h` (doc 08 §10), ajustable, et à terme
  différenciable par format/brûleur.
- Le raffinement par détection de pentes est un travail d'analyse de série
  temporelle à itérer avec les vraies données du prototype ; la v1 pose la
  structure (stockage de `debit_g_par_h` observé) et la formule.

# Historique, analyses et température — conception

*Document de conception. Étend le foyer avec un historique unifié, un tableau
d'analyses (consommation, dépenses), et l'exploitation du capteur de température
(ADR 0011). Identité visuelle : orange (voir `_reference_design/`).*

## 1. Historique unifié (foyer)

Une timeline des événements du foyer, tous sites confondus (ou filtrée par site) :
- **Recharges / commandes** (créée, confirmée, livrée) et leur coût.
- **Paiements** (Mobile Money / à la livraison).
- **Alertes** (seuil bas, sécurité température, propositions).
- **Sessions de cuisson** (début/fin, durée) — via le capteur température.
- **Jalons de niveau** (bouteille changée, tare calibrée fiable).

Endpoint : `GET /api/historique?site_uuid?&depuis?&type?` → liste paginée
d'événements `{ type, date, titre, detail, montant?, icone }`, triés récents d'abord.

## 2. Analyses (tableau de bord foyer)

Endpoint : `GET /api/analyse?site_uuid?&periode=mois|semaine|annee` → agrégats :
- **Consommation** : masse de gaz consommée sur la période (kg), tendance vs
  période précédente (%).
- **Dépenses** : total dépensé (FCFA) sur la période (somme des commandes/
  paiements), tendance.
- **Recharges** : nombre, coût moyen, fréquence (jours entre recharges).
- **Répartition** : consommation/dépense **par bouteille** et **par site** (pour
  un donut).
- **Jours de cuisine** : nombre de jours avec au moins une session de cuisson, et
  une **série journalière** (pour un calendrier heatmap) — via le capteur temp.
- **Autonomie moyenne** et **projection** : « prochaine recharge estimée dans X
  jours » au rythme observé.
- **Série temporelle** de niveau / consommation (pour une courbe).

Toutes ces valeurs se calculent à partir de l'existant (`mesures`, `commandes`,
`livraisons`, `paiements`) + `temperatures`/`sessions_cuisson`. SQL portable
(agrégation en PHP par période, comme `AgregationRegionale`).

## 3. Température & cuisson (ADR 0011)

- `GET /api/sites/{uuid}/temperature` → température courante + `cuisson_en_cours`.
- Sessions de cuisson dans l'historique (§1) et comptées dans les analyses (§2).
- Alerte sécurité `temperature_elevee` (notification foyer).

## 4. Écrans (app foyer, identité orange)

- **Accueil** restylé orange : carte héro dégradé orange (autonomie en heures),
  + un aperçu « ce mois » (consommation, dépense) et l'état cuisson.
- **Historique** (nouvel onglet) : timeline unifiée, filtrable, style « recent
  transactions » (icône, titre, date, montant/þstatut).
- **Analyse** (nouvel écran/onglet) : stat tiles (consommation, dépense, recharges),
  **donut** de répartition, **calendrier heatmap** des jours de cuisine, courbe de
  consommation, projection prochaine recharge.
- **Température** : sur l'accueil et le détail bouteille, température de la cuisine
  + badge « cuisson en cours » + alerte sécurité si trop élevée.

## 5. Web (dashboards)

Les dashboards mandataire/distributeur adoptent aussi l'identité orange ; les
analyses régionales existantes (Phase 5) restent, restylées.

## 6. Cloisonnement / sécurité

Historique, analyses et température restent **strictement dans le périmètre du
foyer** (via `site_acces`). Aucune donnée individuelle ne remonte au distributeur
(agrégats de zone seulement, inchangé).

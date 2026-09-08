# ADR 0005 — Posture d'écriture et mass-assignment

Date : 2026-09-08
Statut : accepté (suite à l'audit sécurité Phase 1)

## Contexte

Le scaffolding initial des modèles a utilisé `#[Guarded([])]` partout (tout
mass-assignable), pratique pour les factories et seeders. L'audit sécurité de la
Phase 1 a montré que c'est dangereux dès que des endpoints d'écriture existeront
(Phases 3+) : un client pourrait forcer des colonnes d'autorisation
(`role`, `niveau`, `*_id` de tenant, `parent_id`, `statut_paiement`,
`commission_g`, `tare_fiable`, `secret_hash`…) et casser le cloisonnement.

## Décision

1. **Tables d'autorisation verrouillées immédiatement.** `Membership` et
   `SiteAcces` (qui *sont* le mécanisme d'autorisation) passent en
   `$fillable = []` : aucune colonne mass-assignable. Elles ne sont créées que
   par du code serveur explicite (`forceCreate` dans seeders/tests ; contrôleurs
   après contrôle d'accès en Phase 3).

2. **Règle standing pour tout endpoint d'écriture (Phase 3+).** Chaque écriture
   passe par un **FormRequest** (ou DTO) avec une allow-list explicite. Les
   colonnes d'autorisation ne sont **jamais** copiées depuis la requête : elles
   sont dérivées côté serveur après vérification de la policy. Interdiction de
   `Model::create($request->all())`.

3. **`$fillable` explicite par modèle** sera introduit sur les modèles restants
   au moment où leurs endpoints d'écriture sont créés (défense en profondeur),
   plutôt qu'en une passe prématurée qui casserait factories/seeders sans
   bénéfice immédiat (aucun endpoint en Phase 1).

4. **Secrets non sérialisés.** `Plateau.secret_hash` est masqué (`Hidden`).

## Conséquences

- Le cloisonnement ne dépend plus de la seule discipline des contrôleurs pour
  les deux tables les plus sensibles.
- Une règle claire encadre les Phases 3+ ; un test de non-régression vérifie que
  `Membership`/`SiteAcces` ne sont pas mass-assignables.
- À l'ouverture de chaque phase d'écriture, l'agent securite vérifie que les
  nouveaux endpoints respectent le point 2.

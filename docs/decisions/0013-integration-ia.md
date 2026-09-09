# ADR 0013 — Intégration de l'IA (assistant et insights) via Claude

Date : 2026-09-09
Statut : accepté

## Contexte

Yagaz produit beaucoup de données exploitables (consommation, dépense,
autonomie, cuisson/température, tensions par zone). Le porteur souhaite
**brancher l'IA à l'intérieur du produit**, en commençant par **Claude
(API Anthropic)** comme modèle. Objectifs de valeur, par ordre de priorité :

1. **Insights foyer** : transformer les agrégats de l'écran Analyse en conseils
   clairs en langage naturel (« pourquoi ta conso a augmenté », « quand
   recommander », astuces d'économie).
2. **Assistant foyer** (conversationnel) : répondre aux questions du foyer sur
   ses propres bouteilles/consommation.
3. **Sécurité cuisine** : lecture des anomalies de température (gaz laissé
   allumé, surchauffe) → message d'alerte proactif.
4. **Prévision de la demande** (mandataire/distributeur) : anticiper les
   tensions par zone.

Ce premier jet ne livre que la **brique 1 (insights foyer)** de bout en bout ;
les briques 2-4 réutiliseront la même abstraction.

## Décision

### Abstraction provider (analogue au paiement, ADR 0010)

Un contrat `App\Contracts\Ia\IaProvider` avec une méthode de génération de texte
(`generer(string $instructions, string $message): string`). Deux implémentations :

- **`SimulateurIa`** (défaut) : aucune requête réseau, réponse déterministe -
  permet de faire tourner l'app, les tests et la CI **sans clé**.
- **`ClaudeProvider`** : appelle l'**API Anthropic Messages** (modèle configurable,
  défaut `claude-sonnet-5` pour un bon rapport qualité/coût, `claude-opus-5`
  possible pour des cas complexes). Activé par `IA_PROVIDER=claude` + clé.

Le binding se fait dans `AppServiceProvider::register()` selon
`config('ia.provider')`. Brancher Claude = poser `IA_PROVIDER=claude` et
`IA_CLAUDE_API_KEY`, sans toucher aux contrôleurs ni aux services métier.

### Où l'IA s'exécute : côté serveur uniquement

**Tous les appels IA partent de l'API Laravel**, jamais des clients (app mobile
/ web). Conséquence non négociable :

- **Aucune clé API dans l'app ou le web** (elles seraient extractibles). La clé
  vit uniquement en variable d'environnement serveur.
- Le client appelle un endpoint Yagaz (`GET /api/analyse/insights`), qui
  construit le prompt, appelle le provider, et renvoie le texte.

### MCP vs API directe

Pour ces cas d'usage (le serveur appelle le modèle avec un prompt cadré), l'**API
Messages directe** suffit et reste la plus simple/économique. **MCP** deviendra
utile plus tard si l'on veut donner au modèle des *outils* (interroger la base,
déclencher une commande) - envisagé pour l'assistant conversationnel (brique 2),
hors périmètre de ce premier jet.

### Confidentialité et cloisonnement

- On n'envoie au modèle que des **agrégats déjà visibles par l'utilisateur**
  (les chiffres de son propre écran Analyse), **jamais** d'identifiants
  (`user_id`, `site_id`, `bouteille_id`, téléphone) ni de données d'autrui.
- Le périmètre reste cloisonné foyer (mêmes règles que `AnalyseController` :
  `ResoutPerimetreFoyer`).
- Les prompts n'incluent pas de PII ; les réponses sont du conseil, jamais une
  décision automatique irréversible.

### Maîtrise du coût et robustesse

- `max_tokens` borné (config) ; prompts courts (agrégats, pas d'historique brut).
- Timeout réseau court ; **toute erreur IA est non bloquante** : on retombe sur
  `SimulateurIa` (ou un message neutre), jamais un 500 à l'utilisateur.
- Throttle dédié sur les endpoints IA (limiter le coût et l'abus).
- (Ultérieur) cache/débounce des insights par période pour éviter de régénérer à
  chaque ouverture.

## Conséquences

- Nouveau contrat + 2 implémentations + `config/ia.php` + binding ; endpoint
  `GET /api/analyse/insights` (brique 1) et un service `AssistantFoyer` qui
  construit le prompt à partir de `AgregationAnalyse`.
- Par défaut (`IA_PROVIDER=simulateur`), rien ne change pour l'exploitation :
  pas de clé, pas d'appel réseau, tests verts.
- Brancher Claude est une bascule d'environnement, réversible, sans refonte.
- Les briques 2 (assistant + MCP), 3 (sécurité cuisine) et 4 (prévision zone)
  s'appuieront sur la même abstraction.

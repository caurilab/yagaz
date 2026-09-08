# Rapport de phase — Phase 3 : Application foyer

Date : 2026-09-08

## Objectif

Livrer l'expérience foyer : voir le niveau et l'autonomie de ses bouteilles,
gérer plusieurs bouteilles et plusieurs sites, distinguer active/secours,
enregistrer une bouteille, recevoir des alertes, rester utile hors ligne. Poser
le contrat d'API qui relie l'app au backend.

## Construit

### Contrat d'API v1 — périmètre foyer (`docs/09-contrat-api.md`)
Frontière backend ↔ clients : endpoints, formats, objet `niveau` et états
visuels partagés, règles active/secours, hors ligne, enveloppe JSON.

### Backend (`yagaz-api`)
- **Auth Sanctum** : inscription (foyer), connexion, déconnexion, `me`.
- **Sites** : liste (via `site_acces`), création, modification, **partage
  d'accès** (cas « surveiller un proche »).
- **Bouteilles** : liste avec objet `niveau` (état visuel, autonomie en heures,
  fraîcheur, estimation), enregistrement, **permutation active/secours
  transactionnelle**, liaison plateau (actif + même site), suppression.
- **Formats**, **alertes** (liste + statut), **réglages d'alerte**, **dépôts
  proches** (haversine, lecture seule).
- FormRequests (colonnes d'autorisation dérivées serveur, ADR 0005), API
  Resources, `NiveauPresenter` (objet niveau réutilisable).

### App foyer (`yagaz-app`, React Native / Expo)
- Client API typé (calqué sur le contrat), auth par token sécurisé
  (`expo-secure-store`), contexte de données avec **cache hors ligne**
  (AsyncStorage) et statut de synchronisation — jamais de page blanche.
- Écrans : connexion/inscription, **accueil** (autonomie en heures en héro, état
  visuel, bandeau hors-ligne, mention « estimation »), bouteilles (permutation),
  enregistrement guidé, détail, alertes, réglages. Navigation protégée, sélecteur
  multi-sites. Palette corail/blanc, tirets normaux.
- Mode démo (fixtures conformes) pour prévisualiser sans backend.

## Sécurité (audit Opus + corrections)
Audit de l'API foyer mené en Opus. Verdict : cloisonnement inter-foyers et
mass-assignment **corrects et testés** ; aucun finding critique. Corrigé :
- **Rate-limiting** (absent) : limiteur strict sur login/register + global.
- **Normalisation E.164 du téléphone** (identifiant pivot) : fin des doublons de
  comptes et de l'unicité contournable ; couvre aussi l'énumération (via throttle).
- Fuites réduites : réponse de partage minimale, `/depots` sans stock exact ni
  coordonnées, suppression d'une route résiduelle exposant le modèle brut.
- Protection du **dernier propriétaire** d'un site.
- Défense en profondeur : `$fillable` explicite sur Site/Bouteille.
- Login durci (hash constant-time, mot de passe borné).

## Tests
- **81 tests, 259 assertions, tout vert** (dont phases précédentes).
- API foyer : auth, cloisonnement HTTP (404), permutation active/secours,
  partage, throttle (429), normalisation téléphone, tentatives de
  mass-assignment HTTP, plateau cross-site, prise de contrôle par partage,
  non-fuite `/depots`.
- App : `tsc --noEmit` vert.
- CI verte (migrations Timescale + suite complète + build web + typecheck app).

## Décisions / conventions
- Enveloppe JSON documentée (`data` pour les ressources, plat pour l'auth).
- Tare saisie manuellement considérée fiable immédiatement (pesée directe).
- Préférences d'alerte (canaux + livreur habituel) portées sur `users`.

## Limites / dette
- App non exécutée en simulateur dans cette session (build/typecheck seulement) ;
  un aperçu visuel peut être produit à la demande.
- Courbe de niveau (historique de mesures) : endpoint câblé, écran en placeholder.
- Notifications push réelles (FCM/APNs) : structure d'alerte prête côté données,
  l'envoi multi-canal (push/SMS/WhatsApp) sera branché avec le service de
  notification (Phase 4/5).
- La commande de recharge (création) est en Phase 4 ; la Phase 3 n'expose que la
  lecture des dépôts proches.

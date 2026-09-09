<?php

use App\Http\Controllers\AlerteController;
use App\Http\Controllers\AnalyseController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BouteilleController;
use App\Http\Controllers\CommandeController;
use App\Http\Controllers\DepotCommandeController;
use App\Http\Controllers\DepotController;
use App\Http\Controllers\DepotStockController;
use App\Http\Controllers\DistributeurController;
use App\Http\Controllers\EquipementController;
use App\Http\Controllers\FormatBouteilleController;
use App\Http\Controllers\HistoriqueController;
use App\Http\Controllers\LivraisonController;
use App\Http\Controllers\LivreurController;
use App\Http\Controllers\MandataireController;
use App\Http\Controllers\MarqueController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaiementController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\TemperatureController;
use App\Http\Controllers\TourneeController;
use Illuminate\Support\Facades\Route;

// Point de contrôle de santé de l'API, sans authentification.
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'yagaz-api',
        'time' => now()->toIso8601String(),
    ]);
});

// === Authentification (contrat API, §« Authentification ») =============
// register/login sont les seules routes ouvertes (contrat API,
// §« Conventions générales »). Throttle strict par IP+téléphone (limiteur
// `auth`, défini dans AppServiceProvider::boot()) : freine le bruteforce de
// mot de passe et l'énumération de téléphones déjà inscrits via `register`
// (audit sécurité, [ÉLEVÉ] rate-limiting, [MOYEN] énumération register).

Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:auth');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:auth');

// === Paiement — webhook Mobile Money (ADR 0010, v2 brique 1) ============
// Route PUBLIQUE, hors `auth:sanctum` : un opérateur/agrégateur n'a pas de
// session Sanctum. Sécurisée uniquement par la vérification de signature
// (`PaymentProvider::verifierNotification`) — jamais par l'authentification.
Route::post('/paiements/webhook/{provider}', [PaiementController::class, 'webhook']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    // Throttle dédié (audit sécurité, [FAIBLE] throttle réglages d'alerte) :
    // `livreur_habituel` valide `exists:users,telephone` (oracle
    // d'énumération) — limiteur `reglages-alertes` (AppServiceProvider::boot()).
    Route::patch('/me/reglages-alertes', [AlerteController::class, 'reglagesAlertes'])->middleware('throttle:reglages-alertes');

    // === Sites (contrat API, §« Sites ») ================================
    Route::get('/sites', [SiteController::class, 'index']);
    Route::post('/sites', [SiteController::class, 'store']);
    Route::get('/sites/{site:uuid}', [SiteController::class, 'show']);
    Route::patch('/sites/{site:uuid}', [SiteController::class, 'update']);
    Route::post('/sites/{site:uuid}/partages', [SiteController::class, 'partager']);
    Route::delete('/sites/{site:uuid}/partages/{user:uuid}', [SiteController::class, 'retirerPartage']);
    // Désignation du livreur habituel PAR SITE (ADR 0009, maillons A/C) : à
    // ne pas confondre avec `PATCH /me/reglages-alertes` (préférence de
    // compte, `users.livreur_habituel_user_id`, qui ne gouverne aucun
    // maillon automatique).
    Route::post('/sites/{site:uuid}/livreur-habituel', [SiteController::class, 'designerLivreurHabituel']);
    Route::delete('/sites/{site:uuid}/livreur-habituel', [SiteController::class, 'retirerLivreurHabituel']);
    // Température de cuisine (ADR 0011) : état courant + cuisson en cours.
    Route::get('/sites/{site:uuid}/temperature', [TemperatureController::class, 'show']);
    // Analyses température/cuisson (ADR 0011, extension) : courbe horaire,
    // pic, histogramme des cuissons par heure, fréquence — cloisonné site.
    Route::get('/sites/{site:uuid}/temperature/analyse', [TemperatureController::class, 'analyse']);
    // Note de sécurité cuisine en langage naturel (ADR 0013, brique 3) :
    // throttle dédié, même logique que `/analyse/insights` (brique 1).
    Route::get('/sites/{site:uuid}/temperature/insights', [TemperatureController::class, 'insights'])->middleware('throttle:20,1');

    // === Historique unifié et analyses foyer (doc 13, §1 et §2) ==========
    // Timeline (commandes/paiements/alertes/cuisson) et agrégats de
    // consommation/dépense — cloisonnés au périmètre foyer (`site_acces`).
    Route::get('/historique', [HistoriqueController::class, 'index']);
    Route::get('/analyse', [AnalyseController::class, 'index']);
    // Insights foyer en langage naturel (ADR 0013, brique 1) : throttle dédié
    // (indépendant du throttle global `api`) pour limiter le coût/l'abus des
    // appels IA.
    Route::get('/analyse/insights', [AnalyseController::class, 'insights'])->middleware('throttle:20,1');

    // === Équipements (contrat API, §« Équipements » — ADR 0012) =========
    // Registre unifié balance/température/écran, cloisonné par
    // `EquipementPolicy` (accès au site d'affectation, ou créateur tant
    // que non affecté). Les capacités dérivées (`a_balance`/`a_temperature`/
    // `a_ecran`) sont exposées sur `SiteResource`.
    Route::get('/equipements', [EquipementController::class, 'index']);
    Route::post('/equipements', [EquipementController::class, 'store']);
    Route::patch('/equipements/{equipement:uuid}', [EquipementController::class, 'update']);
    Route::delete('/equipements/{equipement:uuid}', [EquipementController::class, 'destroy']);

    // === Formats (contrat API, §« Formats ») ============================
    Route::get('/formats', [FormatBouteilleController::class, 'index']);

    // === Marques (contrat API, §« Marques ») ============================
    // Référentiel pour le sélecteur de marque à l'enregistrement d'une
    // bouteille.
    Route::get('/marques', [MarqueController::class, 'index']);

    // === Bouteilles (contrat API, §« Bouteilles ») ======================
    Route::get('/sites/{site:uuid}/bouteilles', [BouteilleController::class, 'index']);
    Route::post('/sites/{site:uuid}/bouteilles', [BouteilleController::class, 'store']);
    Route::get('/bouteilles/{bouteille:uuid}', [BouteilleController::class, 'show']);
    Route::patch('/bouteilles/{bouteille:uuid}', [BouteilleController::class, 'update']);
    Route::post('/bouteilles/{bouteille:uuid}/plateau', [BouteilleController::class, 'attacherPlateau']);
    Route::delete('/bouteilles/{bouteille:uuid}/plateau', [BouteilleController::class, 'detacherPlateau']);
    Route::delete('/bouteilles/{bouteille:uuid}', [BouteilleController::class, 'destroy']);

    // === Alertes (contrat API, §« Alertes ») ============================
    Route::get('/alertes', [AlerteController::class, 'index']);
    Route::patch('/alertes/{alerte}', [AlerteController::class, 'update']);

    // === Recharge (contrat API, §« Recharge ») — lecture seule Phase 3 ==
    Route::get('/depots', [DepotController::class, 'index']);

    // === Rôles (contrat API doc 10, §1) =================================
    Route::get('/mes-roles', [RoleController::class, 'mesRoles']);

    // === Foyer — commandes (contrat API doc 10, §3) =====================
    Route::get('/commandes', [CommandeController::class, 'index']);
    Route::post('/commandes', [CommandeController::class, 'store']);
    Route::get('/commandes/{commande:uuid}', [CommandeController::class, 'show']);
    Route::post('/commandes/{commande:uuid}/reponse', [CommandeController::class, 'reponse']);
    // Suivi/ETA (timeline d'étapes + estimation avant livraison) : même accès
    // que `show` (`CommandePolicy::view`).
    Route::get('/commandes/{commande:uuid}/suivi', [CommandeController::class, 'suivi']);
    // Paiement Mobile Money (ADR 0010, v2 brique 1) : réservé au foyer
    // propriétaire (policy `payer`) d'une commande `confirmee`.
    Route::post('/commandes/{commande:uuid}/paiement', [PaiementController::class, 'initier']);
    Route::get('/commandes/{commande:uuid}/paiement', [PaiementController::class, 'show']);
    // Reçu de paiement (données seulement, pas de PDF) : réservé au foyer
    // propriétaire (même policy `payer`).
    Route::get('/commandes/{commande:uuid}/recu', [PaiementController::class, 'recu']);

    // === Dépôt — commandes et livraison (contrat API doc 10, §4) ========
    Route::patch('/commandes/{commande:uuid}/preparer', [CommandeController::class, 'preparer']);
    Route::post('/commandes/{commande:uuid}/livraison', [CommandeController::class, 'livraison']);
    // Confirmation/ajustement d'un réappro par le dépôt demandeur (ADR 0009, maillon D).
    Route::post('/commandes/{commande:uuid}/confirmer-reappro', [CommandeController::class, 'confirmerReappro']);

    // === Dépôt — stock, file entrante, propositions (contrat API doc 10, §4)
    Route::get('/depots/{organisation:uuid}/stocks', [DepotStockController::class, 'index']);
    Route::patch('/depots/{organisation:uuid}/stocks/{formatBouteille}', [DepotStockController::class, 'update']);
    Route::get('/depots/{organisation:uuid}/livreurs', [DepotController::class, 'livreurs']);
    Route::get('/depots/{organisation:uuid}/commandes', [DepotCommandeController::class, 'index']);
    Route::post('/depots/{organisation:uuid}/propositions', [DepotCommandeController::class, 'propositions']);
    // File des foyers en tension de la zone de desserte (ADR 0009, maillon B).
    Route::get('/depots/{organisation:uuid}/foyers-en-tension', [DepotCommandeController::class, 'foyersEnTension']);
    // Réappros proposés/confirmés du dépôt (ADR 0009, maillon D).
    Route::get('/depots/{organisation:uuid}/reappros', [DepotCommandeController::class, 'reappros']);

    // === Livreur — missions, propositions (contrat API doc 10, §5 ; ADR 0009 maillon C)
    Route::get('/livreur/missions', [LivreurController::class, 'missions']);
    // File actionnable des foyers habituels en tension (ADR 0008, précision « maillon C »).
    Route::get('/livreur/foyers-en-tension', [LivreurController::class, 'foyersEnTension']);
    Route::post('/livreur/propositions', [LivreurController::class, 'propositions']);
    Route::patch('/livraisons/{livraison}/statut', [LivraisonController::class, 'statut']);

    // === Mandataire — dépôts, réappros, tournées (contrat API doc 11, §1)
    Route::get('/mandataires/{organisation:uuid}/depots', [MandataireController::class, 'depots']);
    Route::post('/mandataires/{organisation:uuid}/depots', [MandataireController::class, 'storeDepot']);
    Route::get('/mandataires/{organisation:uuid}/reappros', [MandataireController::class, 'reappros']);
    Route::get('/mandataires/{organisation:uuid}/tournees', [MandataireController::class, 'tournees']);
    Route::post('/mandataires/{organisation:uuid}/tournees', [MandataireController::class, 'storeTournee']);
    Route::get('/mandataires/{organisation:uuid}/livreurs', [MandataireController::class, 'livreurs']);
    Route::patch('/tournees/{tournee:uuid}', [TourneeController::class, 'update']);

    // === Distributeur — demande régionale agrégée (contrat API doc 11, §2)
    Route::get('/distributeurs/{organisation:uuid}/demande', [DistributeurController::class, 'demande']);
    Route::get('/distributeurs/{organisation:uuid}/zones', [DistributeurController::class, 'zones']);
    Route::get('/distributeurs/{organisation:uuid}/volumes', [DistributeurController::class, 'volumes']);

    // === Notifications (contrat API doc 11, §3) =========================
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{alerte}', [NotificationController::class, 'update']);
});

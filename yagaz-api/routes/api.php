<?php

use App\Http\Controllers\AlerteController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BouteilleController;
use App\Http\Controllers\DepotController;
use App\Http\Controllers\FormatBouteilleController;
use App\Http\Controllers\SiteController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Point de contrôle de santé de l'API, sans authentification.
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'yagaz-api',
        'time' => now()->toIso8601String(),
    ]);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// === Authentification (contrat API, §« Authentification ») =============
// register/login sont les seules routes ouvertes (contrat API,
// §« Conventions générales »).

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::patch('/me/reglages-alertes', [AlerteController::class, 'reglagesAlertes']);

    // === Sites (contrat API, §« Sites ») ================================
    Route::get('/sites', [SiteController::class, 'index']);
    Route::post('/sites', [SiteController::class, 'store']);
    Route::get('/sites/{site:uuid}', [SiteController::class, 'show']);
    Route::patch('/sites/{site:uuid}', [SiteController::class, 'update']);
    Route::post('/sites/{site:uuid}/partages', [SiteController::class, 'partager']);
    Route::delete('/sites/{site:uuid}/partages/{user:uuid}', [SiteController::class, 'retirerPartage']);

    // === Formats (contrat API, §« Formats ») ============================
    Route::get('/formats', [FormatBouteilleController::class, 'index']);

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
});

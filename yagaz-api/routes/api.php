<?php

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

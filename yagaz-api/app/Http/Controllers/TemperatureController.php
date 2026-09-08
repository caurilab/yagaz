<?php

namespace App\Http\Controllers;

use App\Models\SessionCuisson;
use App\Models\Site;
use App\Models\Temperature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * État courant de la température de cuisine du foyer (ADR 0011). Cloisonné
 * comme les autres ressources du périmètre foyer : `site_acces`
 * (`SitePolicy::view`), 404 hors périmètre.
 */
class TemperatureController extends Controller
{
    public function show(Request $request, Site $site): JsonResponse
    {
        abort_unless($request->user()->can('view', $site), 404);

        $derniereTemperature = Temperature::where('site_id', $site->id)
            ->orderByDesc('mesure_at')
            ->orderByDesc('seq')
            ->first();

        $sessionOuverte = SessionCuisson::where('site_id', $site->id)
            ->whereNull('fin_at')
            ->latest('debut_at')
            ->first();

        return response()->json([
            'temp_courante_c' => $derniereTemperature?->temp_c,
            // « Fraîche » : dernière lecture reçue il y a moins de 30 minutes
            // (sinon l'affichage doit se méfier d'une donnée périmée — plateau
            // hors ligne notamment).
            'frais' => $derniereTemperature !== null
                && $derniereTemperature->mesure_at->greaterThan(now()->subMinutes(30)),
            'cuisson_en_cours' => $sessionOuverte !== null,
            'debut_cuisson_at' => $sessionOuverte?->debut_at?->toIso8601String(),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\TemperatureAnalyseRequest;
use App\Models\SessionCuisson;
use App\Models\Site;
use App\Models\Temperature;
use App\Services\Temperature\AgregationTemperature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * État courant et analyses de la température de cuisine du foyer (ADR 0011).
 * Cloisonné comme les autres ressources du périmètre foyer : `site_acces`
 * (`SitePolicy::view`), 404 hors périmètre.
 */
class TemperatureController extends Controller
{
    public function __construct(private readonly AgregationTemperature $agregation) {}

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

    /**
     * Analyses température/cuisson du site (ADR 0011, extension) : courbe
     * horaire, pic de température, histogramme des cuissons par heure,
     * heure/période de pointe, fréquence de cuisson et série journalière —
     * voir `AgregationTemperature::analyser()` pour le détail de chaque
     * calcul.
     */
    public function analyse(TemperatureAnalyseRequest $request, Site $site): JsonResponse
    {
        abort_unless($request->user()->can('view', $site), 404);

        $periode = $request->validated('periode') ?? 'mois';

        return response()->json(['data' => $this->agregation->analyser($site, $periode)]);
    }
}

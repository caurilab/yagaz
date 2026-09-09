<?php

namespace App\Http\Controllers;

use App\Http\Requests\TemperatureAnalyseRequest;
use App\Models\SessionCuisson;
use App\Models\Site;
use App\Models\Temperature;
use App\Services\Temperature\AgregationTemperature;
use App\Services\Temperature\AssistantCuisine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

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

    /**
     * Note de sécurité cuisine en langage naturel (ADR 0013, brique 3) :
     * mêmes paramètres et même cloisonnement que `analyse()`. Non bloquant
     * (ADR 0013, §Coût et robustesse) : toute erreur IA retombe sur un
     * message neutre plutôt qu'un 500.
     */
    public function insights(TemperatureAnalyseRequest $request, Site $site, AssistantCuisine $assistant): JsonResponse
    {
        abort_unless($request->user()->can('view', $site), 404);

        $periode = $request->validated('periode') ?? 'mois';

        try {
            $texte = $assistant->noteSecurite($site, $periode);
        } catch (Throwable) {
            $texte = 'Conseils de sécurité indisponibles pour le moment.';
        }

        return response()->json(['data' => ['insights' => $texte, 'periode' => $periode]]);
    }
}

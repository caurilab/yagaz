<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResoutPerimetreFoyer;
use App\Http\Requests\AnalyseIndexRequest;
use App\Services\Analyse\AgregationAnalyse;
use App\Services\Analyse\AssistantFoyer;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Tableau d'analyses du foyer (doc 13, §2) : consommation, dépense,
 * recharges, répartition, jours de cuisine, autonomie et projection —
 * strictement cloisonné au périmètre foyer (`site_acces`).
 */
class AnalyseController extends Controller
{
    use ResoutPerimetreFoyer;

    public function __construct(private readonly AgregationAnalyse $agregation) {}

    public function index(AnalyseIndexRequest $request): JsonResponse
    {
        $siteIds = $this->perimetreSiteIds($request->user(), $request->validated('site_uuid'));
        $periode = $request->validated('periode') ?? 'mois';

        return response()->json(['data' => $this->agregation->analyser($siteIds, $periode)]);
    }

    /**
     * Insights foyer en langage naturel (ADR 0013, brique 1) : mêmes
     * paramètres et même cloisonnement que `index()`. Non bloquant (ADR
     * 0013, §Coût et robustesse) : toute erreur IA retombe sur un message
     * neutre plutôt qu'un 500.
     */
    public function insights(AnalyseIndexRequest $request, AssistantFoyer $assistant): JsonResponse
    {
        $siteIds = $this->perimetreSiteIds($request->user(), $request->validated('site_uuid'));
        $periode = $request->validated('periode') ?? 'mois';

        try {
            $texte = $assistant->insights($siteIds, $periode);
        } catch (Throwable) {
            $texte = 'Conseils indisponibles pour le moment.';
        }

        return response()->json(['data' => ['insights' => $texte, 'periode' => $periode]]);
    }
}

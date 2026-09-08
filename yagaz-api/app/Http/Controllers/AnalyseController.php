<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResoutPerimetreFoyer;
use App\Http\Requests\AnalyseIndexRequest;
use App\Services\Analyse\AgregationAnalyse;
use Illuminate\Http\JsonResponse;

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
}

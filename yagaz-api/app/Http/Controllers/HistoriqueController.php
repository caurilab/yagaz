<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResoutPerimetreFoyer;
use App\Http\Requests\HistoriqueIndexRequest;
use App\Services\Historique\AgregationHistorique;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Historique unifié du foyer (doc 13, §1) : fusionne commandes, paiements,
 * alertes et sessions de cuisson en une timeline triée récent→ancien,
 * strictement cloisonnée au périmètre foyer (`site_acces`).
 */
class HistoriqueController extends Controller
{
    use ResoutPerimetreFoyer;

    private const int PAR_PAGE_DEFAUT = 20;

    public function __construct(private readonly AgregationHistorique $agregation) {}

    public function index(HistoriqueIndexRequest $request): JsonResponse
    {
        $siteIds = $this->perimetreSiteIds($request->user(), $request->validated('site_uuid'));

        $depuisBrut = $request->validated('depuis');
        $depuis = $depuisBrut !== null ? CarbonImmutable::parse((string) $depuisBrut) : null;

        $evenements = $this->agregation->evenements($siteIds, $depuis, $request->validated('type'));

        $parPage = (int) ($request->validated('par_page') ?? self::PAR_PAGE_DEFAUT);
        $page = (int) ($request->validated('page') ?? 1);

        $donnees = $evenements->forPage($page, $parPage)
            ->map(fn (array $evenement) => $this->serialiser($evenement))
            ->values();

        $paginateur = new LengthAwarePaginator($donnees, $evenements->count(), $parPage, $page);

        return response()->json([
            'data' => $paginateur->items(),
            'pagination' => [
                'page' => $paginateur->currentPage(),
                'par_page' => $paginateur->perPage(),
                'total' => $paginateur->total(),
                'dernier_page' => $paginateur->lastPage(),
            ],
        ]);
    }

    /**
     * @param  array{type: string, date: CarbonImmutable, titre: string, detail: ?string, montant: ?int, statut: ?string, icone: string}  $evenement
     * @return array<string, mixed>
     */
    private function serialiser(array $evenement): array
    {
        return [
            'type' => $evenement['type'],
            'date' => $evenement['date']->toIso8601String(),
            'titre' => $evenement['titre'],
            'detail' => $evenement['detail'],
            'montant' => $evenement['montant'],
            'statut' => $evenement['statut'],
            'icone' => $evenement['icone'],
        ];
    }
}

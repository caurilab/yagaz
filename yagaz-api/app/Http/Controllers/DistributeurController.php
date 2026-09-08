<?php

namespace App\Http\Controllers;

use App\Http\Requests\DistributeurPeriodeRequest;
use App\Models\Organisation;
use App\Services\Distributeur\AgregationRegionale;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Demande régionale agrégée d'un distributeur (contrat API doc 11, §2).
 * Accès réservé au membre `distributeur` de l'organisation
 * (`OrganisationPolicy::gererDistributeur`) ; les agrégats ne portent que sur
 * SA branche (mandataires enfants → leurs dépôts). **Jamais** de donnée
 * individuelle de foyer : aucune réponse ne contient `site_id`, `user_id` ni
 * `bouteille_id` (doc 04 §8, doc 07 §9) — voir `AgregationRegionale`.
 */
class DistributeurController extends Controller
{
    public function __construct(private readonly AgregationRegionale $agregation) {}

    /**
     * Demande agrégée : volumes (commandes livrées / réappros) par zone et
     * par format, dans le temps.
     */
    public function demande(DistributeurPeriodeRequest $request, Organisation $organisation): JsonResponse
    {
        abort_unless($request->user()->can('gererDistributeur', $organisation), 404);

        [$depuis, $jusqua, $pas] = $this->fenetre($request);

        return response()->json(['data' => $this->agregation->demande($organisation, $depuis, $jusqua, $pas)]);
    }

    /**
     * Tensions par zone (dépôts en rupture, vides accumulés) — carte de
     * chaleur.
     */
    public function zones(Request $request, Organisation $organisation): JsonResponse
    {
        abort_unless($request->user()->can('gererDistributeur', $organisation), 404);

        return response()->json(['data' => $this->agregation->zones($organisation)]);
    }

    /**
     * Évolution des volumes distribués, par zone (comparaison, saisonnalité).
     */
    public function volumes(DistributeurPeriodeRequest $request, Organisation $organisation): JsonResponse
    {
        abort_unless($request->user()->can('gererDistributeur', $organisation), 404);

        [$depuis, $jusqua, $pas] = $this->fenetre($request);

        return response()->json(['data' => $this->agregation->volumes($organisation, $depuis, $jusqua, $pas)]);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string}
     */
    private function fenetre(DistributeurPeriodeRequest $request): array
    {
        $depuis = CarbonImmutable::parse((string) $request->validated('depuis'))->startOfDay();
        $jusqua = CarbonImmutable::parse((string) $request->validated('jusqua'))->endOfDay();
        $pas = $request->validated('pas') ?? 'jour';

        return [$depuis, $jusqua, $pas];
    }
}

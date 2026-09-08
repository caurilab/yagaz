<?php

namespace App\Http\Controllers;

use App\Enums\StatutTournee;
use App\Http\Requests\TourneeUpdateRequest;
use App\Http\Resources\TourneeResource;
use App\Models\Tournee;
use App\Models\User;
use App\Services\Tournee\CycleTournee;

/**
 * Ajustement/validation d'une tournée par le mandataire (contrat API doc 11,
 * `PATCH /api/tournees/{uuid}`). La machine à états (progression du statut,
 * remplacement des lignes) est centralisée dans `CycleTournee`.
 */
class TourneeController extends Controller
{
    public function __construct(private readonly CycleTournee $cycle) {}

    public function update(TourneeUpdateRequest $request, Tournee $tournee): TourneeResource
    {
        abort_unless($request->user()->can('update', $tournee), 404);

        $statut = $request->has('statut') ? StatutTournee::from($request->validated('statut')) : null;

        $livreurFourni = $request->has('livreur_user_id');
        $livreurUuid = $request->validated('livreur_user_id');
        $livreur = $livreurFourni && $livreurUuid !== null ? User::where('uuid', $livreurUuid)->firstOrFail() : null;

        $lignes = $request->has('lignes') ? $request->validated('lignes') : null;

        $tourneeMiseAJour = $this->cycle->ajuster($tournee, $statut, $livreur, $livreurFourni, $lignes);

        return new TourneeResource($tourneeMiseAJour->load(['livreur', 'lignes.depot', 'lignes.format']));
    }
}

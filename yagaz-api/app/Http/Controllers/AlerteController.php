<?php

namespace App\Http\Controllers;

use App\Enums\StatutAlerte;
use App\Http\Requests\AlerteIndexRequest;
use App\Http\Requests\AlerteUpdateRequest;
use App\Http\Requests\ReglagesAlertesRequest;
use App\Http\Resources\AlerteResource;
use App\Http\Resources\UserResource;
use App\Models\Alerte;
use App\Models\Bouteille;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Alertes du périmètre foyer (contrat API, §« Alertes ») et préférences de
 * notification du compte (`PATCH /api/me/reglages-alertes`).
 */
class AlerteController extends Controller
{
    public function index(AlerteIndexRequest $request): AnonymousResourceCollection
    {
        $siteIds = $request->user()->sites()->pluck('sites.id');
        $bouteilleIds = Bouteille::whereIn('site_id', $siteIds)->pluck('id');

        $alertes = Alerte::whereIn('bouteille_id', $bouteilleIds)
            ->when($request->validated('statut'), fn ($query, $statut) => $query->where('statut', $statut))
            ->orderByDesc('created_at')
            ->get();

        return AlerteResource::collection($alertes);
    }

    public function update(AlerteUpdateRequest $request, Alerte $alerte): AlerteResource
    {
        abort_unless($request->user()->can('update', $alerte), 404);

        $alerte->statut = StatutAlerte::from($request->validated('statut'));
        $alerte->save();

        return new AlerteResource($alerte);
    }

    public function reglagesAlertes(ReglagesAlertesRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $user->canaux_alerte = $validated['canaux'];

        if (array_key_exists('livreur_habituel', $validated)) {
            $telephone = $validated['livreur_habituel'];
            $user->livreur_habituel_user_id = $telephone !== null
                ? User::where('telephone', $telephone)->value('id')
                : null;
        }

        $user->save();

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }
}

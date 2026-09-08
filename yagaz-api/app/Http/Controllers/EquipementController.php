<?php

namespace App\Http\Controllers;

use App\Enums\StatutEquipement;
use App\Http\Requests\EquipementStoreRequest;
use App\Http\Requests\EquipementUpdateRequest;
use App\Http\Resources\EquipementResource;
use App\Models\Equipement;
use App\Models\Plateau;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Registre unifié des équipements du compte (contrat API, §« Équipements » —
 * ADR 0012). Cloisonné par `EquipementPolicy` : accès au site d'affectation,
 * ou créateur tant que non affecté. 404 hors périmètre.
 */
class EquipementController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $siteIds = $user->sites()->pluck('sites.id');

        $equipements = Equipement::whereIn('site_id', $siteIds)
            ->orWhere(function ($query) use ($user): void {
                $query->whereNull('site_id')->where('cree_par', $user->id);
            })
            ->with('site')
            ->get();

        return EquipementResource::collection($equipements);
    }

    public function store(EquipementStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $site = null;

        if (isset($validated['site_uuid'])) {
            $site = Site::where('uuid', $validated['site_uuid'])->first();
            abort_if($site === null, 404);
            abort_unless($user->aAccesAuSite($site), 404);
        }

        // Un équipement dont la référence correspond à l'uid d'un plateau
        // déjà provisionné est relié d'emblée (ADR 0012) : l'app voit alors
        // les données sans étape de connexion supplémentaire.
        $relie = Plateau::where('uid', $validated['reference'])->exists();

        $equipement = new Equipement([
            'type' => $validated['type'],
            'reference' => $validated['reference'],
        ]);
        $equipement->forceFill([
            'site_id' => $site?->id,
            'statut' => $relie ? StatutEquipement::Actif : StatutEquipement::AConnecter,
            'cree_par' => $user->id,
        ]);
        $equipement->save();

        return (new EquipementResource($equipement->fresh('site')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(EquipementUpdateRequest $request, Equipement $equipement): EquipementResource
    {
        $user = $request->user();
        abort_unless($user->can('update', $equipement), 404);

        $validated = $request->validated();

        if (array_key_exists('site_uuid', $validated)) {
            if ($validated['site_uuid'] === null) {
                $equipement->site_id = null;
            } else {
                $site = Site::where('uuid', $validated['site_uuid'])->first();
                abort_if($site === null, 404);
                abort_unless($user->aAccesAuSite($site), 404);
                $equipement->site_id = $site->id;
            }
        }

        if (array_key_exists('statut', $validated)) {
            $equipement->statut = StatutEquipement::from($validated['statut']);
        }

        $equipement->save();

        return new EquipementResource($equipement->fresh('site'));
    }

    public function destroy(Request $request, Equipement $equipement): Response
    {
        abort_unless($request->user()->can('delete', $equipement), 404);

        $equipement->delete();

        return response()->noContent();
    }
}

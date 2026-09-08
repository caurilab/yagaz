<?php

namespace App\Http\Controllers;

use App\Enums\NiveauAcces;
use App\Enums\StatutAlerte;
use App\Http\Controllers\Concerns\AutoriseCloisonnement;
use App\Http\Requests\SitePartageRequest;
use App\Http\Requests\SiteStoreRequest;
use App\Http\Requests\SiteUpdateRequest;
use App\Http\Resources\SiteResource;
use App\Http\Resources\UserResource;
use App\Models\Alerte;
use App\Models\Site;
use App\Models\SiteAcces;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Sites du périmètre foyer (contrat API, §« Sites »). Cloisonné par
 * `site_acces` (doc 07, §3 et §10) : `SitePolicy` + `AutoriseCloisonnement`
 * distinguent 404 (aucun accès) de 403 (accès insuffisant).
 */
class SiteController extends Controller
{
    use AutoriseCloisonnement;

    public function index(Request $request): AnonymousResourceCollection
    {
        $sites = $request->user()->sites()->get();
        $sites->each(fn (Site $site) => $this->hydrater($site, $request->user()));

        return SiteResource::collection($sites);
    }

    public function store(SiteStoreRequest $request): JsonResponse
    {
        $site = Site::create([
            ...$request->validated(),
            'cree_par' => $request->user()->id,
        ]);

        SiteAcces::forceCreate([
            'site_id' => $site->id,
            'user_id' => $request->user()->id,
            'niveau' => NiveauAcces::Proprietaire->value,
        ]);

        return (new SiteResource($this->hydrater($site, $request->user())))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Site $site): SiteResource
    {
        abort_unless($request->user()->can('view', $site), 404);

        return new SiteResource($this->hydrater($site, $request->user()));
    }

    public function update(SiteUpdateRequest $request, Site $site): SiteResource
    {
        $this->autoriserSite($request->user(), $site, 'update');

        $site->update($request->validated());

        return new SiteResource($this->hydrater($site->fresh(), $request->user()));
    }

    public function partager(SitePartageRequest $request, Site $site): JsonResponse
    {
        $this->autoriserSite($request->user(), $site, 'partager');

        $beneficiaire = User::where('telephone', $request->validated('telephone'))->firstOrFail();
        $niveau = $request->validated('niveau');

        $acces = SiteAcces::where('site_id', $site->id)->where('user_id', $beneficiaire->id)->first();

        if ($acces !== null) {
            $acces->forceFill(['niveau' => $niveau])->save();
        } else {
            SiteAcces::forceCreate([
                'site_id' => $site->id,
                'user_id' => $beneficiaire->id,
                'niveau' => $niveau,
            ]);
        }

        return response()->json([
            'message' => 'Accès partagé.',
            'utilisateur' => new UserResource($beneficiaire),
            'niveau' => $niveau,
        ], 201);
    }

    public function retirerPartage(Request $request, Site $site, User $user): Response
    {
        $this->autoriserSite($request->user(), $site, 'partager');

        SiteAcces::where('site_id', $site->id)->where('user_id', $user->id)->delete();

        return response()->noContent();
    }

    /**
     * Hydrate un site des attributs calculés attendus par `SiteResource`
     * (`niveau_acces`, `bouteilles_count`, `a_alerte_active`) — pas des
     * colonnes du modèle, construits ici pour rester au plus près de
     * l'utilisateur courant.
     */
    private function hydrater(Site $site, User $user): Site
    {
        $bouteilleIds = $site->bouteilles()->pluck('id');

        $site->setAttribute('niveau_acces', $user->siteAcces()->where('site_id', $site->id)->value('niveau'));
        $site->setAttribute('bouteilles_count', $bouteilleIds->count());
        $site->setAttribute('a_alerte_active', Alerte::whereIn('bouteille_id', $bouteilleIds)
            ->whereIn('statut', [StatutAlerte::Emise->value, StatutAlerte::Vue->value])
            ->exists());

        return $site;
    }
}

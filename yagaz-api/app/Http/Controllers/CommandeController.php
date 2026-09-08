<?php

namespace App\Http\Controllers;

use App\Http\Requests\CommandeLivraisonRequest;
use App\Http\Requests\CommandeReponseRequest;
use App\Http\Requests\CommandeStoreRequest;
use App\Http\Resources\CommandeResource;
use App\Http\Resources\LivraisonResource;
use App\Models\Commande;
use App\Models\FormatBouteille;
use App\Models\Organisation;
use App\Models\Site;
use App\Models\User;
use App\Services\Commande\CycleCommande;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Commandes du périmètre foyer (contrat API doc 10, §3) et actions du dépôt
 * sur une commande (§4, `preparer`/`livraison`) : la machine à états est
 * centralisée dans `CycleCommande`, jamais dans ce contrôleur.
 */
class CommandeController extends Controller
{
    private const array RELATIONS = ['site', 'format', 'cibleOrg', 'livraison.livreur'];

    public function __construct(private readonly CycleCommande $cycle) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $siteIds = $request->user()->sites()->pluck('sites.id');

        $commandes = Commande::whereIn('site_id', $siteIds)
            ->with(self::RELATIONS)
            ->orderByDesc('created_at')
            ->get();

        return CommandeResource::collection($commandes);
    }

    public function store(CommandeStoreRequest $request): JsonResponse
    {
        $user = $request->user();

        $site = Site::where('uuid', $request->validated('site_uuid'))->firstOrFail();
        abort_unless($user->aAccesAuSite($site), 404);

        $depot = Organisation::where('uuid', $request->validated('depot_uuid'))->firstOrFail();
        $format = FormatBouteille::findOrFail($request->validated('format_id'));

        $commande = $this->cycle->creerDepuisFoyer($site, $depot, $format, (int) $request->validated('quantite'), $user);

        return (new CommandeResource($commande->load(self::RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Commande $commande): CommandeResource
    {
        abort_unless($request->user()->can('view', $commande), 404);

        return new CommandeResource($commande->load(self::RELATIONS));
    }

    public function reponse(CommandeReponseRequest $request, Commande $commande): CommandeResource
    {
        abort_unless($request->user()->can('repondre', $commande), 404);

        $this->cycle->repondre($commande, (bool) $request->validated('accepte'));

        return new CommandeResource($commande->fresh(self::RELATIONS));
    }

    public function preparer(Request $request, Commande $commande): CommandeResource
    {
        abort_unless($request->user()->can('gererDepot', $commande), 404);

        $this->cycle->preparer($commande);

        return new CommandeResource($commande->fresh(self::RELATIONS));
    }

    public function livraison(CommandeLivraisonRequest $request, Commande $commande): JsonResponse
    {
        abort_unless($request->user()->can('gererDepot', $commande), 404);

        $livreurUuid = $request->validated('livreur_user_id');
        $livreur = $livreurUuid !== null ? User::where('uuid', $livreurUuid)->firstOrFail() : null;

        $livraison = $this->cycle->affecterLivreur($commande, $livreur);

        return (new LivraisonResource($livraison->load('livreur')))
            ->response()
            ->setStatusCode(201);
    }
}

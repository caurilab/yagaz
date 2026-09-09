<?php

namespace App\Http\Controllers;

use App\Enums\TypeCommande;
use App\Http\Requests\CommandeConfirmerReapproRequest;
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
use App\Services\Commande\SuiviCommande;
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

    public function __construct(
        private readonly CycleCommande $cycle,
        private readonly SuiviCommande $suiviCommande,
    ) {}

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

        $typeBrut = $request->validated('type');
        $type = $typeBrut !== null ? TypeCommande::from($typeBrut) : TypeCommande::Echange;

        $commande = $this->cycle->creerDepuisFoyer($site, $depot, $format, (int) $request->validated('quantite'), $user);
        $commande->type = $type;
        $commande->save();

        return (new CommandeResource($commande->load(self::RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Commande $commande): CommandeResource
    {
        abort_unless($request->user()->can('view', $commande), 404);

        return new CommandeResource($commande->load(self::RELATIONS));
    }

    /**
     * `GET /api/commandes/{uuid}/suivi` : timeline d'étapes + ETA estimée
     * (contrat API). Même périmètre d'accès que `show` (`CommandePolicy::view`)
     * — le foyer demandeur ou l'organisation cible.
     */
    public function suivi(Request $request, Commande $commande): JsonResponse
    {
        abort_unless($request->user()->can('view', $commande), 404);

        return response()->json(['data' => $this->suiviCommande->suivi($commande)]);
    }

    public function reponse(CommandeReponseRequest $request, Commande $commande): CommandeResource
    {
        abort_unless($request->user()->can('repondre', $commande), 404);

        $this->cycle->repondre($commande, (bool) $request->validated('accepte'));

        return new CommandeResource($commande->fresh(self::RELATIONS));
    }

    /**
     * Le dépôt demandeur confirme/ajuste un réappro `proposee` → `confirmee`
     * (ADR 0009, maillon D ; contrat API doc 11 §1).
     */
    public function confirmerReappro(CommandeConfirmerReapproRequest $request, Commande $commande): CommandeResource
    {
        abort_unless($request->user()->can('confirmerReappro', $commande), 404);

        $quantite = $request->validated('quantite');

        $this->cycle->confirmerReappro($commande, $quantite !== null ? (int) $quantite : null);

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

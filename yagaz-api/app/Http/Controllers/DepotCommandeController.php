<?php

namespace App\Http\Controllers;

use App\Http\Requests\DepotCommandeIndexRequest;
use App\Http\Requests\DepotPropositionRequest;
use App\Http\Resources\CommandeResource;
use App\Models\Commande;
use App\Models\FormatBouteille;
use App\Models\Organisation;
use App\Models\Site;
use App\Services\Commande\CycleCommande;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * File des commandes entrantes d'un dépôt et propositions vers un foyer
 * (contrat API doc 10, §4). Accès réservé au membre `gerant_depot` de
 * l'organisation, périmètre borné à l'org (404 hors périmètre).
 */
class DepotCommandeController extends Controller
{
    public function __construct(private readonly CycleCommande $cycle) {}

    public function index(DepotCommandeIndexRequest $request, Organisation $organisation): AnonymousResourceCollection
    {
        abort_unless($request->user()->can('gererDepot', $organisation), 404);

        $commandes = Commande::where('cible_org_id', $organisation->id)
            ->when($request->validated('statut'), fn ($query, $statut) => $query->where('statut', $statut))
            ->with(['site', 'format', 'livraison.livreur'])
            ->orderBy('created_at')
            ->get();

        return CommandeResource::collection($commandes);
    }

    public function propositions(DepotPropositionRequest $request, Organisation $organisation): JsonResponse
    {
        abort_unless($request->user()->can('gererDepot', $organisation), 404);

        $site = Site::where('uuid', $request->validated('site_uuid'))->firstOrFail();
        $format = FormatBouteille::findOrFail($request->validated('format_id'));

        $commande = $this->cycle->proposer($organisation, $site, $format, (int) $request->validated('quantite'));

        return (new CommandeResource($commande->load(['site', 'format', 'cibleOrg'])))
            ->response()
            ->setStatusCode(201);
    }
}

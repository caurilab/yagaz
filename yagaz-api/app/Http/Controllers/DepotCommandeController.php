<?php

namespace App\Http\Controllers;

use App\Enums\OrigineCommande;
use App\Enums\RoleMembership;
use App\Http\Requests\DepotCommandeIndexRequest;
use App\Http\Requests\DepotPropositionRequest;
use App\Http\Resources\CommandeResource;
use App\Http\Resources\FoyerEnTensionResource;
use App\Http\Resources\ReapproResource;
use App\Models\Commande;
use App\Models\FormatBouteille;
use App\Models\Organisation;
use App\Models\Site;
use App\Services\Commande\CycleCommande;
use App\Services\Commande\DetectionTension;
use App\Services\Geo\Distance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;

/**
 * File des commandes entrantes d'un dépôt et propositions vers un foyer
 * (contrat API doc 10, §4). Accès réservé au membre `gerant_depot` de
 * l'organisation, périmètre borné à l'org (404 hors périmètre).
 */
class DepotCommandeController extends Controller
{
    public function __construct(
        private readonly CycleCommande $cycle,
        private readonly DetectionTension $tension,
        private readonly Distance $distance,
    ) {}

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

    /**
     * Réappros du dépôt (ADR 0009, maillon D ; contrat API doc 11 §1,
     * `GET /api/depots/{orgUuid}/reappros`) : commandes `origine = depot`
     * dont ce dépôt est le DEMANDEUR — pour l'écran de confirmation/
     * ajustement, y compris les `proposee` (pas encore confirmées),
     * contrairement à la vue du mandataire.
     */
    public function reappros(DepotCommandeIndexRequest $request, Organisation $organisation): AnonymousResourceCollection
    {
        abort_unless($request->user()->can('gererDepot', $organisation), 404);

        $reappros = Commande::where('demandeur_org_id', $organisation->id)
            ->where('origine', OrigineCommande::Depot->value)
            ->when($request->validated('statut'), fn ($query, $statut) => $query->where('statut', $statut))
            ->with(['cibleOrg', 'format'])
            ->orderByDesc('created_at')
            ->get();

        return ReapproResource::collection($reappros);
    }

    /**
     * Un dépôt ne peut proposer une livraison qu'à un site de sa **zone de
     * desserte** (ADR 0009, maillon B) : déjà « client » — au moins une
     * commande existante ciblant ce dépôt (heuristique v1, audit sécurité
     * Phase 4, [MOYEN]) — OU rattaché par un livreur habituel membre de ce
     * dépôt (cohérent avec la file `foyersEnTension()`). Sinon 404 (pas 422 :
     * ne révèle pas si le site existe ailleurs, hors du périmètre du dépôt).
     */
    public function propositions(DepotPropositionRequest $request, Organisation $organisation): JsonResponse
    {
        abort_unless($request->user()->can('gererDepot', $organisation), 404);

        $site = Site::where('uuid', $request->validated('site_uuid'))
            ->whereIn('id', $this->sitesDeLaZoneDeDesserte($organisation))
            ->first();
        abort_if($site === null, 404);

        $format = FormatBouteille::findOrFail($request->validated('format_id'));

        $commande = $this->cycle->proposer($organisation, $site, $format, (int) $request->validated('quantite'));

        return (new CommandeResource($commande->load(['site', 'format', 'cibleOrg'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * File des foyers en tension de la zone de desserte du dépôt (ADR 0009,
     * maillon B) : sites de `sitesDeLaZoneDeDesserte()` ayant une bouteille
     * active sous son seuil bas (`DetectionTension`). Réponse minimale et
     * actionnable (ADR 0008) : jamais le niveau exact, l'historique, le
     * contact, ni les autres bouteilles/sites du foyer.
     */
    public function foyersEnTension(Request $request, Organisation $organisation): AnonymousResourceCollection
    {
        abort_unless($request->user()->can('gererDepot', $organisation), 404);

        $sites = Site::whereIn('id', $this->sitesDeLaZoneDeDesserte($organisation))
            ->get()
            ->each(fn (Site $site) => $site->setAttribute('bouteille_en_tension', $this->tension->bouteilleEnTension($site)))
            ->filter(fn (Site $site) => $site->bouteille_en_tension !== null)
            ->each(fn (Site $site) => $site->setAttribute('distance_km', $this->distanceDepuisLeDepot($organisation, $site)))
            ->values();

        return FoyerEnTensionResource::collection($sites);
    }

    /**
     * Zone de desserte v1 du dépôt (ADR 0009, maillon B) : sites déjà
     * clients (au moins une commande ciblant ce dépôt) OU rattachés par un
     * livreur habituel actif, membre `livreur` de ce dépôt — jamais un
     * foyer inconnu du dépôt (étanchéité).
     *
     * @return Collection<int, int>
     */
    private function sitesDeLaZoneDeDesserte(Organisation $organisation): Collection
    {
        $clients = Commande::where('cible_org_id', $organisation->id)->pluck('site_id')->filter();

        $rattaches = Site::whereHas('livreurHabituel', fn ($livreurHabituel) => $livreurHabituel
            ->where('actif', true)
            ->whereHas('livreur', fn ($livreur) => $livreur->whereHas('memberships', fn ($membership) => $membership
                ->where('organisation_id', $organisation->id)
                ->where('role', RoleMembership::Livreur->value)
                ->where('actif', true))))
            ->pluck('id');

        return $clients->merge($rattaches)->unique()->values();
    }

    private function distanceDepuisLeDepot(Organisation $organisation, Site $site): ?float
    {
        if ($organisation->lat === null || $organisation->lng === null) {
            return null;
        }

        return $this->distance->kilometres(
            (float) $organisation->lat,
            (float) $organisation->lng,
            $site->lat !== null ? (float) $site->lat : null,
            $site->lng !== null ? (float) $site->lng : null,
        );
    }
}

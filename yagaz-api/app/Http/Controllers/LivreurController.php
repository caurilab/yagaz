<?php

namespace App\Http\Controllers;

use App\Enums\RoleMembership;
use App\Enums\TypeOrganisation;
use App\Http\Requests\LivreurMissionsIndexRequest;
use App\Http\Requests\LivreurPropositionRequest;
use App\Http\Resources\CommandeResource;
use App\Http\Resources\LivraisonMissionResource;
use App\Models\Livraison;
use App\Models\Organisation;
use App\Models\Site;
use App\Models\User;
use App\Services\Commande\CycleCommande;
use App\Services\Commande\DetectionTension;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;

/**
 * Missions du livreur (contrat API doc 10, §5) : un livreur ne voit QUE ses
 * propres livraisons affectées — filtrées directement par `livreur_user_id`,
 * pas de fuite possible vers les missions d'un autre livreur.
 */
class LivreurController extends Controller
{
    public function __construct(
        private readonly CycleCommande $cycle,
        private readonly DetectionTension $tension,
    ) {}

    public function missions(LivreurMissionsIndexRequest $request): AnonymousResourceCollection
    {
        $livraisons = Livraison::where('livreur_user_id', $request->user()->id)
            ->when($request->validated('statut'), fn ($query, $statut) => $query->where('statut', $statut))
            ->with(['commande.site', 'commande.format'])
            ->orderBy('created_at')
            ->get();

        return LivraisonMissionResource::collection($livraisons);
    }

    /**
     * Le livreur propose une livraison pour un de ses foyers habituels en
     * tension (ADR 0009, maillon C). Droit ouvert UNIQUEMENT pour ses
     * foyers habituels en tension (autorisation tracée,
     * `SitePolicy::proposerCommeLivreurHabituel`) : 404 si le site n'existe
     * pas, 403 s'il n'en est pas le livreur habituel actif, 422 si le site
     * n'est pas en tension ou si le dépôt cible ne peut pas être résolu sans
     * ambiguïté. La commande créée est `proposee`, ciblant le dépôt dont le
     * livreur est membre — le foyer confirme ensuite (mécanisme existant,
     * `CycleCommande::repondre`).
     */
    public function propositions(LivreurPropositionRequest $request): JsonResponse
    {
        $livreur = $request->user();

        $site = Site::where('uuid', $request->validated('site_uuid'))->first();
        abort_if($site === null, 404);

        abort_unless($livreur->can('proposerCommeLivreurHabituel', $site), 403);

        $bouteille = $this->tension->bouteilleEnTension($site);
        abort_if($bouteille === null, 422, "Ce site n'est pas en tension.");

        $depot = $this->resoudreDepotCible($livreur, $request->validated('depot_uuid'));

        $quantite = (int) ($request->validated('quantite') ?? 1);

        $commande = $this->cycle->proposer($depot, $site, $bouteille->format, $quantite);

        return (new CommandeResource($commande->load(['site', 'format', 'cibleOrg'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Dépôt cible de la proposition (ADR 0009, maillon C) : le dépôt précisé
     * par `depot_uuid` (vérifié comme dépôt du livreur), ou l'unique dépôt
     * dont il est livreur si `depot_uuid` est omis. 422 si le livreur n'est
     * livreur d'aucun dépôt, de plusieurs sans précision, ou pas du dépôt
     * précisé.
     */
    private function resoudreDepotCible(User $livreur, ?string $depotUuid): Organisation
    {
        /** @var Collection<int, Organisation> $depots */
        $depots = $livreur->organisations()
            ->where('organisations.type', TypeOrganisation::Depot->value)
            ->wherePivot('role', RoleMembership::Livreur->value)
            ->wherePivot('actif', true)
            ->get();

        if ($depotUuid !== null) {
            $depot = $depots->firstWhere('uuid', $depotUuid);
            abort_if($depot === null, 422, "Vous n'êtes pas livreur de ce dépôt.");

            return $depot;
        }

        abort_unless($depots->count() === 1, 422, 'Précisez le dépôt cible (depot_uuid) : vous êtes livreur de plusieurs dépôts, ou d\'aucun.');

        return $depots->first();
    }
}

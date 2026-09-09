<?php

namespace App\Http\Controllers;

use App\Enums\OrigineCommande;
use App\Enums\TypeOrganisation;
use App\Http\Requests\DepotCommandeIndexRequest;
use App\Http\Requests\MandataireDepotStoreRequest;
use App\Http\Requests\MandataireTourneeStoreRequest;
use App\Http\Resources\DepotConsolideResource;
use App\Http\Resources\ReapproResource;
use App\Http\Resources\TourneeResource;
use App\Models\Commande;
use App\Models\MouvementStock;
use App\Models\Organisation;
use App\Models\Tournee;
use App\Models\User;
use App\Services\Tournee\CycleTournee;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;

/**
 * Vue consolidée des dépôts d'un mandataire, réappros, et tournées (contrat
 * API doc 11, §1). Accès réservé au membre `mandataire` de l'organisation
 * (`OrganisationPolicy::gererMandataire`), périmètre borné à ses dépôts
 * enfants (`parent_id`) — toute autre organisation est hors périmètre (404).
 */
class MandataireController extends Controller
{
    public function __construct(private readonly CycleTournee $cycle) {}

    /**
     * Vue consolidée de tous les dépôts du mandataire (doc 11, §1) : stock
     * plein/vide par format, tensions, dernière activité.
     */
    public function depots(Request $request, Organisation $organisation): AnonymousResourceCollection
    {
        abort_unless($request->user()->can('gererMandataire', $organisation), 404);

        $depots = Organisation::where('parent_id', $organisation->id)
            ->where('type', TypeOrganisation::Depot->value)
            ->with(['stocks.format'])
            ->get()
            ->each(function (Organisation $depot) {
                $depot->setAttribute('derniere_activite', $this->derniereActivite($depot));
            });

        return DepotConsolideResource::collection($depots);
    }

    /**
     * Crée un dépôt rattaché au mandataire (contrat API doc 11, §1). Le
     * dépôt naît sans stock (le gérant l'approvisionnera) ; on renvoie la
     * même vue consolidée que la liste, avec des stocks vides.
     */
    public function storeDepot(MandataireDepotStoreRequest $request, Organisation $organisation): JsonResponse
    {
        abort_unless($request->user()->can('gererMandataire', $organisation), 404);

        $depot = Organisation::create([
            'type' => TypeOrganisation::Depot,
            'parent_id' => $organisation->id,
            'nom' => $request->validated('nom'),
            'zone' => $request->validated('zone'),
        ]);

        $depot->setRelation('stocks', Collection::make());
        $depot->setAttribute('derniere_activite', null);

        return (new DepotConsolideResource($depot))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Réappros (ADR 0009, maillon D ; contrat API doc 11, §1) : commandes
     * d'origine `depot` ciblant ce mandataire — produites automatiquement
     * par `PreparationReappro` quand un dépôt passe en tension, confirmées
     * par lui via `POST /commandes/{uuid}/confirmer-reappro`. Les `confirmee`
     * (les fermes) sont mises en avant ; une `proposee` reste visible ici
     * (elle appartient au périmètre du mandataire) mais n'est pas encore
     * « ferme » tant que le dépôt ne l'a pas confirmée.
     */
    public function reappros(DepotCommandeIndexRequest $request, Organisation $organisation): AnonymousResourceCollection
    {
        abort_unless($request->user()->can('gererMandataire', $organisation), 404);

        $reappros = Commande::where('cible_org_id', $organisation->id)
            ->where('origine', OrigineCommande::Depot->value)
            ->when($request->validated('statut'), fn ($query, $statut) => $query->where('statut', $statut))
            ->with(['demandeurOrg', 'format'])
            ->orderByRaw("case when statut = 'confirmee' then 0 else 1 end")
            ->orderByDesc('created_at')
            ->get();

        return ReapproResource::collection($reappros);
    }

    /**
     * Tournées du mandataire (proposées/validées/en cours/terminées).
     */
    public function tournees(Request $request, Organisation $organisation): AnonymousResourceCollection
    {
        abort_unless($request->user()->can('gererMandataire', $organisation), 404);

        $tournees = Tournee::where('organisation_id', $organisation->id)
            ->with(['livreur', 'lignes.depot', 'lignes.format'])
            ->orderByDesc('date')
            ->get();

        return TourneeResource::collection($tournees);
    }

    /**
     * Crée/valide une tournée à partir de lignes {dépôt, format, pleines,
     * vides à récupérer} (doc 11, §1 : « la plateforme propose, le
     * mandataire valide/ajuste »).
     */
    public function storeTournee(MandataireTourneeStoreRequest $request, Organisation $organisation): JsonResponse
    {
        abort_unless($request->user()->can('gererMandataire', $organisation), 404);

        $livreurUuid = $request->validated('livreur_user_id');
        $livreur = $livreurUuid !== null ? User::where('uuid', $livreurUuid)->firstOrFail() : null;

        $tournee = $this->cycle->creerEtValider(
            $organisation,
            (string) $request->validated('date'),
            $livreur,
            $request->validated('lignes'),
        );

        return (new TourneeResource($tournee->load(['livreur', 'lignes.depot', 'lignes.format'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Dernière activité connue d'un dépôt (doc 11, §1) : le plus récent
     * entre son dernier mouvement de stock et sa dernière commande reçue.
     * Deux requêtes par dépôt (v1) — acceptable pour le petit nombre de
     * dépôts d'un mandataire ; à optimiser (jointure/agrégat continu) si le
     * volume l'exige (doc 11, §2, même logique que les agrégats distributeur).
     */
    private function derniereActivite(Organisation $depot): ?CarbonImmutable
    {
        $stockIds = $depot->stocks->pluck('id');

        $dernierMouvement = $stockIds->isNotEmpty()
            ? MouvementStock::whereIn('stock_id', $stockIds)->max('created_at')
            : null;

        $derniereCommande = Commande::where('cible_org_id', $depot->id)->max('updated_at');

        $dates = Collection::make([$dernierMouvement, $derniereCommande])
            ->filter()
            ->map(fn ($date) => CarbonImmutable::parse($date));

        return $dates->isEmpty() ? null : $dates->max();
    }
}

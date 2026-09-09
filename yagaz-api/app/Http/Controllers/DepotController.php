<?php

namespace App\Http\Controllers;

use App\Enums\RoleMembership;
use App\Enums\TypeOrganisation;
use App\Http\Requests\DepotIndexRequest;
use App\Http\Resources\DepotResource;
use App\Http\Resources\UserPubliqueResource;
use App\Models\FormatBouteille;
use App\Models\Organisation;
use App\Services\Geo\Distance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Dépôts proches ayant un format en stock (contrat API, §« Recharge »).
 * Lecture seule en Phase 3 : la création de commande arrive en Phase 4.
 */
class DepotController extends Controller
{
    public function __construct(private readonly Distance $distance) {}

    public function index(DepotIndexRequest $request): AnonymousResourceCollection
    {
        $lat = (float) $request->validated('lat');
        $lng = (float) $request->validated('lng');
        $formatId = (int) $request->validated('format_id');

        // On commande une TAILLE (code) : un dépôt qui a du B12 d'une autre
        // marque peut fournir la recharge. On matche donc tous les formats du
        // même code que celui demandé, et chaque dépôt renvoie le format qu'il
        // fournira réellement (`format_id`), pour que la commande cible un
        // format qu'il a bien en stock.
        $code = FormatBouteille::query()->whereKey($formatId)->value('code');
        $formatIdsDuCode = $code !== null
            ? FormatBouteille::query()->where('code', $code)->pluck('id')->all()
            : [$formatId];

        $depots = Organisation::query()
            ->where('type', TypeOrganisation::Depot->value)
            ->whereHas('stocks', fn ($query) => $query->whereIn('format_id', $formatIdsDuCode)->where('pleines', '>', 0))
            ->with(['stocks' => fn ($query) => $query->whereIn('format_id', $formatIdsDuCode)->where('pleines', '>', 0)->with('format')])
            ->get();

        $depots = $depots
            ->each(function (Organisation $depot) use ($lat, $lng, $formatId): void {
                $depot->setAttribute(
                    'distance_km',
                    $this->distance->kilometres($lat, $lng, $depot->lat !== null ? (float) $depot->lat : null, $depot->lng !== null ? (float) $depot->lng : null)
                );
                // Marque demandée si ce dépôt l'a en stock, sinon la première
                // marque de cette taille qu'il possède.
                $stockChoisi = $depot->stocks->firstWhere('format_id', $formatId) ?? $depot->stocks->first();
                $depot->setAttribute('format_fulfillable_id', $stockChoisi?->format_id);
                $depot->setAttribute('format_fulfillable_marque', $stockChoisi?->format?->marque);
            })
            ->sortBy('distance_km')
            ->values();

        return DepotResource::collection($depots);
    }

    /**
     * Livreurs rattachés à un dépôt, pour l'affectation d'une livraison
     * (contrat API doc 10, §4). Réservé au gérant du dépôt ; projection
     * minimale (uuid + nom), pas de PII inutile.
     */
    public function livreurs(Request $request, Organisation $organisation): AnonymousResourceCollection
    {
        abort_unless($request->user()->can('gererDepot', $organisation), 404);

        $livreurs = $organisation->users()
            ->wherePivot('role', RoleMembership::Livreur->value)
            ->wherePivot('actif', true)
            ->get();

        return UserPubliqueResource::collection($livreurs);
    }
}

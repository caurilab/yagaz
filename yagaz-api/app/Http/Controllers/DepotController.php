<?php

namespace App\Http\Controllers;

use App\Enums\TypeOrganisation;
use App\Http\Requests\DepotIndexRequest;
use App\Http\Resources\DepotResource;
use App\Models\Organisation;
use App\Services\Geo\Distance;
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

        $depots = Organisation::query()
            ->where('type', TypeOrganisation::Depot->value)
            ->whereHas('stocks', fn ($query) => $query->where('format_id', $formatId)->where('pleines', '>', 0))
            ->with(['stocks' => fn ($query) => $query->where('format_id', $formatId)])
            ->get();

        $depots = $depots
            ->each(fn (Organisation $depot) => $depot->setAttribute(
                'distance_km',
                $this->distance->kilometres($lat, $lng, $depot->lat !== null ? (float) $depot->lat : null, $depot->lng !== null ? (float) $depot->lng : null)
            ))
            ->sortBy('distance_km')
            ->values();

        return DepotResource::collection($depots);
    }
}

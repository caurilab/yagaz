<?php

namespace App\Http\Controllers;

use App\Enums\TypeMouvementStock;
use App\Http\Requests\StockUpdateRequest;
use App\Http\Resources\StockResource;
use App\Models\FormatBouteille;
use App\Models\MouvementStock;
use App\Models\Organisation;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Stock d'un dépôt, par format (contrat API doc 10, §4). Accès réservé au
 * membre `gerant_depot` de l'organisation (`OrganisationPolicy::gererDepot`),
 * périmètre borné à l'org — toute autre organisation est hors périmètre (404).
 */
class DepotStockController extends Controller
{
    public function index(Request $request, Organisation $organisation): AnonymousResourceCollection
    {
        abort_unless($request->user()->can('gererDepot', $organisation), 404);

        $stocks = Stock::where('organisation_id', $organisation->id)
            ->with('format')
            ->get();

        return StockResource::collection($stocks);
    }

    public function update(StockUpdateRequest $request, Organisation $organisation, FormatBouteille $formatBouteille): StockResource
    {
        abort_unless($request->user()->can('gererDepot', $organisation), 404);

        $validated = $request->validated();

        $stock = DB::transaction(function () use ($organisation, $formatBouteille, $validated) {
            $stock = Stock::firstOrCreate(
                ['organisation_id' => $organisation->id, 'format_id' => $formatBouteille->id],
                ['pleines' => 0, 'vides' => 0, 'seuil_plein_bas' => 0]
            );

            $deltaPleines = array_key_exists('pleines', $validated) ? $validated['pleines'] - $stock->pleines : 0;
            $deltaVides = array_key_exists('vides', $validated) ? $validated['vides'] - $stock->vides : 0;

            if (array_key_exists('pleines', $validated)) {
                $stock->pleines = $validated['pleines'];
            }

            if (array_key_exists('vides', $validated)) {
                $stock->vides = $validated['vides'];
            }

            if (array_key_exists('seuil_plein_bas', $validated)) {
                $stock->seuil_plein_bas = $validated['seuil_plein_bas'];
            }

            $stock->save();

            // Ajustement manuel journalisé (contrat API doc 10, §4) : trace
            // uniquement s'il y a une variation réelle de pleines/vides
            // (un simple changement de seuil n'est pas un mouvement de stock).
            if ($deltaPleines !== 0 || $deltaVides !== 0) {
                MouvementStock::create([
                    'stock_id' => $stock->id,
                    'type' => TypeMouvementStock::Ajustement,
                    'delta_pleines' => $deltaPleines,
                    'delta_vides' => $deltaVides,
                    'livraison_id' => null,
                ]);
            }

            return $stock;
        });

        return new StockResource($stock->fresh('format'));
    }
}

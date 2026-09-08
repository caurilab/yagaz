<?php

namespace App\Http\Controllers;

use App\Http\Resources\FormatBouteilleResource;
use App\Models\FormatBouteille;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Référentiel des formats de bouteille (contrat API, §« Formats »).
 */
class FormatBouteilleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return FormatBouteilleResource::collection(
            FormatBouteille::with('marqueRef')->orderBy('marque')->orderBy('code')->get()
        );
    }
}

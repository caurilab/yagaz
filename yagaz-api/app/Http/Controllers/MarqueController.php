<?php

namespace App\Http\Controllers;

use App\Http\Resources\MarqueResource;
use App\Models\Marque;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Référentiel des marques de gaz (contrat API, §« Marques »).
 */
class MarqueController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return MarqueResource::collection(Marque::orderBy('nom')->get());
    }
}

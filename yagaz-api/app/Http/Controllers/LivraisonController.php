<?php

namespace App\Http\Controllers;

use App\Enums\StatutLivraison;
use App\Http\Requests\LivraisonStatutRequest;
use App\Http\Resources\LivraisonResource;
use App\Models\Livraison;
use App\Services\Commande\CycleLivraison;

/**
 * Changement de statut d'une livraison par le livreur (contrat API doc 10,
 * §5). La machine à états (transitions ordonnées, propagation sur la
 * commande, mouvement de stock au retour des vides) est centralisée dans
 * `CycleLivraison`.
 */
class LivraisonController extends Controller
{
    public function __construct(private readonly CycleLivraison $cycle) {}

    public function statut(LivraisonStatutRequest $request, Livraison $livraison): LivraisonResource
    {
        abort_unless($request->user()->can('changerStatut', $livraison), 404);

        $statut = StatutLivraison::from($request->validated('statut'));
        $videsRecuperes = $request->validated('vides_recuperes');

        $livraisonMiseAJour = $this->cycle->changerStatut(
            $livraison,
            $statut,
            $videsRecuperes !== null ? (int) $videsRecuperes : null
        );

        return new LivraisonResource($livraisonMiseAJour->load('livreur'));
    }
}

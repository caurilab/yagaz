<?php

namespace App\Http\Controllers;

use App\Http\Requests\LivreurMissionsIndexRequest;
use App\Http\Resources\LivraisonMissionResource;
use App\Models\Livraison;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Missions du livreur (contrat API doc 10, §5) : un livreur ne voit QUE ses
 * propres livraisons affectées — filtrées directement par `livreur_user_id`,
 * pas de fuite possible vers les missions d'un autre livreur.
 */
class LivreurController extends Controller
{
    public function missions(LivreurMissionsIndexRequest $request): AnonymousResourceCollection
    {
        $livraisons = Livraison::where('livreur_user_id', $request->user()->id)
            ->when($request->validated('statut'), fn ($query, $statut) => $query->where('statut', $statut))
            ->with(['commande.site', 'commande.format'])
            ->orderBy('created_at')
            ->get();

        return LivraisonMissionResource::collection($livraisons);
    }
}

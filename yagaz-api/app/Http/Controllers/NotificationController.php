<?php

namespace App\Http\Controllers;

use App\Enums\StatutAlerte;
use App\Http\Requests\NotificationIndexRequest;
use App\Http\Requests\NotificationUpdateRequest;
use App\Http\Resources\AlerteResource;
use App\Models\Alerte;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Notifications de l'utilisateur courant, tous rôles (contrat API doc 11,
 * §3) : une notification est une alerte adressée nommément
 * (`destinataire_user_id`). Complète `GET /api/alertes` (périmètre foyer,
 * fondé sur l'accès aux sites).
 */
class NotificationController extends Controller
{
    public function index(NotificationIndexRequest $request): AnonymousResourceCollection
    {
        $alertes = Alerte::where('destinataire_user_id', $request->user()->id)
            ->when($request->validated('statut'), fn ($query, $statut) => $query->where('statut', $statut))
            ->with(['bouteille', 'organisation', 'commande', 'site'])
            ->orderByDesc('created_at')
            ->get();

        return AlerteResource::collection($alertes);
    }

    public function update(NotificationUpdateRequest $request, Alerte $alerte): AlerteResource
    {
        abort_unless($request->user()->can('update', $alerte), 404);

        $alerte->statut = StatutAlerte::from($request->validated('statut'));
        $alerte->save();

        return new AlerteResource($alerte);
    }
}

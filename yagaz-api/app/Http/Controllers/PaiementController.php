<?php

namespace App\Http\Controllers;

use App\Http\Resources\PaiementResource;
use App\Models\Commande;
use App\Services\Paiement\PaiementMobileMoney;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Paiement Mobile Money d'une commande (ADR 0010, v2 brique 1) : initiation
 * et consultation réservées au foyer propriétaire (§4 policy `payer`), et
 * webhook PUBLIC vérifié par signature — jamais authentifié, le statut de
 * paiement n'est jamais cru depuis un client (ADR 0010, §Sécurité). Toute la
 * logique métier vit dans `PaiementMobileMoney`, jamais ici.
 */
class PaiementController extends Controller
{
    public function __construct(private readonly PaiementMobileMoney $paiementMobileMoney) {}

    /**
     * `POST /api/commandes/{commande:uuid}/paiement` : le foyer propriétaire
     * initie un paiement Mobile Money pour une commande `confirmee`.
     */
    public function initier(Request $request, Commande $commande): JsonResponse
    {
        abort_unless($request->user()->can('payer', $commande), 404);

        $resultat = $this->paiementMobileMoney->initier($commande);

        return response()->json([
            'data' => [
                'paiement' => new PaiementResource($resultat['paiement']),
                'intention' => $resultat['intention'],
            ],
        ], 201);
    }

    /**
     * `GET /api/commandes/{commande:uuid}/paiement` : statut du paiement le
     * plus récent de la commande, pour le foyer propriétaire.
     */
    public function show(Request $request, Commande $commande): JsonResponse
    {
        abort_unless($request->user()->can('payer', $commande), 404);

        $paiement = $commande->paiement;

        abort_if($paiement === null, 404, 'Aucun paiement pour cette commande.');

        return response()->json(['data' => new PaiementResource($paiement)]);
    }

    /**
     * `POST /api/paiements/webhook/{provider}` : route PUBLIQUE (hors
     * `auth:sanctum`), sécurisée uniquement par la vérification de signature
     * (`PaymentProvider::verifierNotification`) — jamais par une session
     * utilisateur, un webhook opérateur n'en a pas.
     */
    public function webhook(Request $request, string $provider): JsonResponse
    {
        $signature = $request->header('X-Paiement-Signature');

        $this->paiementMobileMoney->traiterWebhook($provider, $request->all(), $signature);

        return response()->json(['status' => 'ok']);
    }
}

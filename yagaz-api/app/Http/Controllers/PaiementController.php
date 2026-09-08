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
        // Borne de taille simple du corps de la notification (défense en
        // profondeur contre un webhook anormalement volumineux) : 64 Ko est
        // largement suffisant pour une notification Mobile Money (quelques
        // champs texte/numériques).
        abort_if(
            strlen($request->getContent()) > 64_000,
            413,
            'Corps de la notification trop volumineux.'
        );

        // NOTE (branchement d'un agrégateur RÉEL) : la signature devra alors
        // être vérifiée sur le corps BRUT de la requête ($request->getContent()),
        // jamais sur $request->all() re-sérialisé — un réencodage JSON peut
        // changer l'ordre des clés, les espaces ou la représentation
        // numérique et invalider une signature calculée par l'opérateur sur
        // le corps original envoyé. Le simulateur actuel (`SimulateurPaiement`)
        // signe lui-même un tableau canonique (clés triées), donc
        // `$request->all()` reste cohérent avec lui pour l'instant ; ce ne
        // sera plus vrai avec un agrégateur réel (Orange Money, MTN MoMo,
        // Moov Money, Wave via un PSP fronting), dont l'implémentation de
        // `PaymentProvider::verifierNotification` devra recevoir le corps
        // brut en plus (ou à la place) du payload décodé.
        //
        // Note config : prévoir à terme un limiteur dédié (ex.
        // `throttle:paiement-webhook` défini dans `AppServiceProvider::boot()`,
        // sur le modèle de `reglages-alertes`) plutôt que de s'appuyer sur le
        // throttle global `api` — non ajouté ici pour ne pas modifier le
        // throttle global.
        $signature = $request->header('X-Paiement-Signature');

        $this->paiementMobileMoney->traiterWebhook($provider, $request->all(), $signature);

        return response()->json(['status' => 'ok']);
    }
}

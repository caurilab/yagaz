<?php

namespace App\Http\Controllers;

use App\Enums\StatutPaiement;
use App\Http\Resources\PaiementResource;
use App\Models\Commande;
use App\Models\Paiement;
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
    /**
     * Même hypothèse de tarif que `PaiementMobileMoney::PRIX_XOF_PAR_BOUTEILLE`
     * (ADR 0010) : aucune grille tarifaire fournie par le PRD à ce jour. Sert
     * uniquement à estimer le montant du reçu « à la livraison » d'une
     * commande jamais payée (le montant réel d'un paiement effectif reste
     * celui de `Paiement::montant`).
     */
    private const int PRIX_XOF_PAR_BOUTEILLE = 6_500;

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
     * `GET /api/commandes/{uuid}/recu` : reçu du dernier paiement `regle` de
     * la commande, ou de son paiement courant si elle n'est pas (encore)
     * réglée ; réservé au foyer propriétaire (même policy `payer` que le
     * paiement lui-même — un reçu engage la même donnée financière). Sans
     * aucun paiement enregistré (`a_la_livraison` jamais initié), renvoie un
     * reçu « à la livraison » cohérent : montant estimé, statut `en_attente`.
     */
    public function recu(Request $request, Commande $commande): JsonResponse
    {
        abort_unless($request->user()->can('payer', $commande), 404);

        $commande->loadMissing(['format', 'cibleOrg', 'site']);

        $paiementRegle = $commande->paiements()
            ->where('statut', StatutPaiement::Regle)
            ->latest('id')
            ->first();

        $paiement = $paiementRegle ?? $commande->paiement;

        return response()->json(['data' => $this->serialiserRecu($commande, $paiement)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialiserRecu(Commande $commande, ?Paiement $paiement): array
    {
        $commandeSerialisee = [
            'uuid' => $commande->uuid,
            'format' => $commande->format !== null ? [
                'code' => $commande->format->code,
                'marque' => $commande->format->marque,
            ] : null,
            'quantite' => $commande->quantite,
            'depot' => $commande->cibleOrg !== null ? ['nom' => $commande->cibleOrg->nom] : null,
        ];

        $site = $commande->site !== null ? ['nom' => $commande->site->nom] : null;

        if ($paiement !== null) {
            return [
                'reference' => $paiement->reference,
                'montant' => $paiement->montant,
                'devise' => $paiement->devise,
                'statut_paiement' => $paiement->statut->value,
                'mode_paiement' => $commande->mode_paiement->value,
                // Le règlement (`updated_at`) fait foi une fois réglé
                // (`PaiementMobileMoney::appliquerStatut` ne sauvegarde qu'à
                // ce moment précis) ; sinon la date d'initiation du paiement.
                'date' => ($paiement->statut === StatutPaiement::Regle ? $paiement->updated_at : $paiement->created_at)
                    ?->toIso8601String(),
                'commande' => $commandeSerialisee,
                'site' => $site,
            ];
        }

        // Aucun paiement enregistré : commande encore « à la livraison »,
        // reçu cohérent avec un montant estimé (même hypothèse de tarif que
        // `PaiementMobileMoney`) et un statut `en_attente`.
        return [
            'reference' => null,
            'montant' => $commande->quantite * self::PRIX_XOF_PAR_BOUTEILLE,
            'devise' => (string) config('paiement.devise'),
            'statut_paiement' => $commande->statut_paiement->value,
            'mode_paiement' => $commande->mode_paiement->value,
            'date' => optional($commande->created_at)->toIso8601String(),
            'commande' => $commandeSerialisee,
            'site' => $site,
        ];
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

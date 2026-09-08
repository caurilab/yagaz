<?php

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentProvider;
use App\Enums\StatutPaiement;
use App\Models\Paiement;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Squelette d'implémentation `PaymentProvider` pour un agrégateur Mobile
 * Money d'Afrique de l'Ouest (type CinetPay/Semoa/PayDunya — couvrant Orange
 * Money, MTN MoMo, Moov Money, Wave via un PSP fronting), en préparation de
 * l'ADR 0010 tant que l'utilisateur n'a pas encore de compte agrégateur.
 *
 * Pour activer ce provider une fois les identifiants disponibles :
 * définir `PAIEMENT_PROVIDER=agregateur` puis renseigner
 * `PAIEMENT_AGREGATEUR_BASE_URL`, `_API_KEY`, `_SITE_ID`, `_SECRET` et
 * `_RETURN_URL` dans `.env` (voir `config/paiement.php`, section `agregateur`).
 *
 * IMPORTANT : le nom exact des endpoints (`/payment/init`, `/payment/check`),
 * des champs de la requête/réponse, et le schéma de signature du webhook
 * (nom du header, champs signés, algorithme) sont GÉNÉRIQUES ici et devront
 * être ajustés à l'agrégateur réellement retenu (CinetPay/Semoa/PayDunya) à
 * partir de sa documentation officielle. Tant que la configuration est
 * incomplète, `initier()`/`statut()` lèvent une exception explicite plutôt
 * que d'effectuer un appel réseau — voir `assertConfigure()`.
 *
 * Le webhook doit vérifier la signature sur le corps BRUT de la requête
 * (voir la note dans `PaiementController::webhook`) : `verifierNotification`
 * reçoit ici le payload déjà décodé par Laravel, ce qui suffit pour un HMAC
 * calculé sur le tableau canonicalisé (comme `SimulateurPaiement`) mais ne
 * conviendra pas forcément à un agrégateur dont la signature porte sur la
 * chaîne JSON brute exacte envoyée — à ajuster selon la doc du fournisseur.
 */
final class AgregateurPaiement implements PaymentProvider
{
    /**
     * Délai (secondes) accordé à l'agrégateur pour répondre — évite qu'un
     * appel réseau bloque indéfiniment l'initiation d'un paiement.
     */
    private const int TIMEOUT_SECONDES = 15;

    public function initier(Paiement $paiement): array
    {
        $this->assertConfigure();

        $reference = $paiement->reference ?: 'AGG-'.Str::upper(Str::uuid()->toString());

        // NOTE : noms de champs génériques (apikey/site_id/transaction_id...)
        // — à ajuster selon l'agrégateur retenu (CinetPay/Semoa/PayDunya).
        $reponse = Http::timeout(self::TIMEOUT_SECONDES)
            ->baseUrl((string) config('paiement.agregateur.base_url'))
            ->post('/payment/init', [
                'apikey' => (string) config('paiement.agregateur.api_key'),
                'site_id' => (string) config('paiement.agregateur.site_id'),
                'transaction_id' => $reference,
                'amount' => $paiement->montant,
                'currency' => $paiement->devise,
                'description' => 'Paiement commande Yagaz',
                'notify_url' => url('/api/paiements/webhook/agregateur'),
                'return_url' => (string) config('paiement.agregateur.return_url'),
            ])
            ->throw();

        $corps = $reponse->json();

        return [
            'reference' => (string) ($corps['transaction_id'] ?? $reference),
            'statut' => StatutPaiement::Initie->value,
            'payment_url' => $corps['payment_url'] ?? $corps['token'] ?? null,
        ];
    }

    public function verifierNotification(array $payload, ?string $signature): bool
    {
        $secret = (string) config('paiement.agregateur.secret');

        if ($signature === null || $signature === '' || $secret === '') {
            return false;
        }

        // NOTE : HMAC générique sur le tableau canonicalisé (clés triées),
        // comme `SimulateurPaiement` — le schéma réel de l'agrégateur retenu
        // (nom du header porteur de la signature, champs effectivement
        // signés, éventuellement le corps brut plutôt que ce tableau
        // redécodé) devra être vérifié dans sa documentation et ajusté ici.
        ksort($payload);
        $attendue = hash_hmac('sha256', json_encode($payload, JSON_THROW_ON_ERROR), $secret);

        return hash_equals($attendue, $signature);
    }

    public function extraireResultat(array $payload): array
    {
        // NOTE : mapping générique des champs de réponse agrégateur — à
        // ajuster selon le schéma réel (ex. `cpm_trans_id`/`cpm_result` pour
        // CinetPay, ou équivalent Semoa/PayDunya).
        return [
            'reference' => (string) ($payload['transaction_id'] ?? $payload['reference'] ?? ''),
            'statut' => $this->normaliserStatut((string) ($payload['status'] ?? $payload['statut'] ?? '')),
            'montant' => (int) ($payload['amount'] ?? $payload['montant'] ?? 0),
            'devise' => (string) ($payload['currency'] ?? $payload['devise'] ?? ''),
        ];
    }

    public function statut(string $reference): string
    {
        $this->assertConfigure();

        $reponse = Http::timeout(self::TIMEOUT_SECONDES)
            ->baseUrl((string) config('paiement.agregateur.base_url'))
            ->post('/payment/check', [
                'apikey' => (string) config('paiement.agregateur.api_key'),
                'site_id' => (string) config('paiement.agregateur.site_id'),
                'transaction_id' => $reference,
            ])
            ->throw();

        $corps = $reponse->json();

        return $this->normaliserStatut((string) ($corps['status'] ?? $corps['statut'] ?? ''));
    }

    /**
     * Traduit un statut brut agrégateur vers une valeur `StatutPaiement`
     * connue de l'application — à ajuster selon les libellés réels renvoyés
     * par l'agrégateur retenu (ex. `ACCEPTED`/`REFUSED` pour CinetPay).
     */
    private function normaliserStatut(string $statutBrut): string
    {
        return match (Str::upper($statutBrut)) {
            'ACCEPTED', 'SUCCESS', 'SUCCESSFUL', 'REGLE' => StatutPaiement::Regle->value,
            'REFUSED', 'FAILED', 'ECHOUE' => StatutPaiement::Echoue->value,
            default => StatutPaiement::Initie->value,
        };
    }

    /**
     * Garde-fou « non configuré » (préparation de la zone avant obtention
     * d'un compte agrégateur) : sans identifiants complets, on refuse tout
     * appel réseau et on lève une exception explicite plutôt que d'échouer
     * silencieusement ou d'appeler un `base_url` vide.
     */
    private function assertConfigure(): void
    {
        $baseUrl = (string) config('paiement.agregateur.base_url');
        $apiKey = (string) config('paiement.agregateur.api_key');
        $siteId = (string) config('paiement.agregateur.site_id');

        if ($baseUrl === '' || $apiKey === '' || $siteId === '') {
            throw new RuntimeException('Agrégateur de paiement non configuré');
        }
    }
}

<?php

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentProvider;
use App\Enums\StatutPaiement;
use App\Models\Paiement;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Implémentation par défaut de `PaymentProvider` (ADR 0010, v2 brique 1,
 * analogue à `CanalLog` pour les notifications) : simule le cycle d'un
 * paiement Mobile Money en local/dev/CI, sans jamais appeler un opérateur
 * réel. Une implémentation « agrégateur réel » (Orange Money, MTN MoMo, Moov
 * Money, Wave via un PSP fronting) la remplacera — liée dans
 * `AppServiceProvider` — sans toucher au cycle de commande ni aux contrôleurs.
 */
final class SimulateurPaiement implements PaymentProvider
{
    /**
     * Statut simulé retenu par défaut tant qu'aucune notification/réconciliation
     * n'a fait avancer une référence — permet de simuler un provider qui n'a
     * encore rien à dire sur une intention fraîchement créée.
     */
    private const string CLE_CACHE_PREFIX = 'paiement.simulateur.statut.';

    public function initier(Paiement $paiement): array
    {
        $reference = 'SIM-'.Str::upper(Str::random(24));

        self::memoriserStatut($reference, StatutPaiement::Initie->value);

        return [
            'reference' => $reference,
            'statut' => StatutPaiement::Initie->value,
            // En réel : push USSD ou lien de paiement transmis au foyer.
            'instructions' => 'Simulation : composez le code reçu par SMS pour valider le paiement.',
        ];
    }

    public function verifierNotification(array $payload, ?string $signature): bool
    {
        $secret = (string) config('paiement.secret_webhook');

        if ($signature === null || $signature === '' || $secret === '') {
            return false;
        }

        return hash_equals(self::signer($payload, $secret), $signature);
    }

    public function extraireResultat(array $payload): array
    {
        return [
            'reference' => (string) ($payload['reference'] ?? ''),
            'statut' => (string) ($payload['statut'] ?? ''),
            'montant' => (int) ($payload['montant'] ?? 0),
            'devise' => (string) ($payload['devise'] ?? ''),
        ];
    }

    public function statut(string $reference): string
    {
        return (string) Cache::get(self::cleCache($reference), StatutPaiement::Initie->value);
    }

    /**
     * Fait évoluer le statut simulé d'une référence — c'est ce qui « permet
     * de simuler succès/échec » (ADR 0010) pour la réconciliation, en
     * l'absence d'un opérateur réel à interroger : utilisé par les tests, et
     * par `traiterWebhook` qui garde ce statut simulé cohérent avec le
     * webhook déjà reçu.
     */
    public static function memoriserStatut(string $reference, string $statut): void
    {
        Cache::put(self::cleCache($reference), $statut, now()->addDay());
    }

    /**
     * Calcule la signature HMAC-SHA256 attendue d'un payload de notification
     * (canonicalisé par tri des clés). Public : sert aux tests pour simuler
     * un webhook signé par le provider.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function signer(array $payload, ?string $secret = null): string
    {
        $secret ??= (string) config('paiement.secret_webhook');

        return hash_hmac('sha256', self::canonique($payload), $secret);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function canonique(array $payload): string
    {
        ksort($payload);

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    private static function cleCache(string $reference): string
    {
        return self::CLE_CACHE_PREFIX.$reference;
    }
}

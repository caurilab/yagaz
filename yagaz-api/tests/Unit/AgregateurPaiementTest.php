<?php

namespace Tests\Unit;

use App\Models\Paiement;
use App\Services\Payment\AgregateurPaiement;
use RuntimeException;
use Tests\TestCase;

/**
 * `AgregateurPaiement` (préparation de la zone agrégateur, ADR 0010) : tant
 * que les identifiants (`base_url`/`api_key`/`site_id`) ne sont pas
 * configurés, aucune méthode ne doit tenter d'appel réseau — elles doivent
 * échouer proprement. Aucun test ici n'appelle un agrégateur réel.
 */
class AgregateurPaiementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'paiement.agregateur.base_url' => '',
            'paiement.agregateur.api_key' => '',
            'paiement.agregateur.site_id' => '',
            'paiement.agregateur.secret' => '',
            'paiement.agregateur.return_url' => '',
        ]);
    }

    public function test_initier_sans_configuration_leve_une_exception_explicite_sans_appel_reseau(): void
    {
        $provider = new AgregateurPaiement;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Agrégateur de paiement non configuré');

        $provider->initier(new Paiement);
    }

    public function test_statut_sans_configuration_leve_une_exception_explicite_sans_appel_reseau(): void
    {
        $provider = new AgregateurPaiement;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Agrégateur de paiement non configuré');

        $provider->statut('AGG-REFERENCE');
    }

    public function test_initier_avec_une_configuration_partielle_reste_non_configure(): void
    {
        config(['paiement.agregateur.base_url' => 'https://agregateur.example']);

        $provider = new AgregateurPaiement;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Agrégateur de paiement non configuré');

        $provider->initier(new Paiement);
    }

    public function test_verifier_notification_avec_une_mauvaise_signature_renvoie_false(): void
    {
        config(['paiement.agregateur.secret' => 'secret-agregateur-test']);

        $provider = new AgregateurPaiement;

        $payload = ['transaction_id' => 'AGG-1', 'status' => 'ACCEPTED', 'amount' => 1000, 'currency' => 'XOF'];

        $this->assertFalse($provider->verifierNotification($payload, 'signature-invalide'));
    }

    public function test_verifier_notification_sans_signature_renvoie_false(): void
    {
        config(['paiement.agregateur.secret' => 'secret-agregateur-test']);

        $provider = new AgregateurPaiement;

        $payload = ['transaction_id' => 'AGG-1', 'status' => 'ACCEPTED', 'amount' => 1000, 'currency' => 'XOF'];

        $this->assertFalse($provider->verifierNotification($payload, null));
    }

    public function test_verifier_notification_sans_secret_configure_renvoie_false(): void
    {
        $provider = new AgregateurPaiement;

        $payload = ['transaction_id' => 'AGG-1', 'status' => 'ACCEPTED', 'amount' => 1000, 'currency' => 'XOF'];

        $this->assertFalse($provider->verifierNotification($payload, 'peu-importe'));
    }

    public function test_verifier_notification_avec_une_signature_valide_renvoie_true(): void
    {
        $secret = 'secret-agregateur-test';
        config(['paiement.agregateur.secret' => $secret]);

        $provider = new AgregateurPaiement;

        $payload = ['transaction_id' => 'AGG-1', 'status' => 'ACCEPTED', 'amount' => 1000, 'currency' => 'XOF'];

        $payloadCanonique = $payload;
        ksort($payloadCanonique);
        $signature = hash_hmac('sha256', json_encode($payloadCanonique, JSON_THROW_ON_ERROR), $secret);

        $this->assertTrue($provider->verifierNotification($payload, $signature));
    }

    public function test_extraire_resultat_mappe_les_champs_agregateur(): void
    {
        $provider = new AgregateurPaiement;

        $resultat = $provider->extraireResultat([
            'transaction_id' => 'AGG-1',
            'status' => 'ACCEPTED',
            'amount' => 13000,
            'currency' => 'XOF',
        ]);

        $this->assertSame([
            'reference' => 'AGG-1',
            'statut' => 'regle',
            'montant' => 13000,
            'devise' => 'XOF',
        ], $resultat);
    }
}

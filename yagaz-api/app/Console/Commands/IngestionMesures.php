<?php

namespace App\Console\Commands;

use App\Services\Mesure\ResultatIngestionMesure;
use App\Services\Mesure\TraitementMesure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Exceptions\MqttClientException;
use PhpMqtt\Client\MqttClient;
use Throwable;

/**
 * Worker d'ingestion des mesures (doc 08 §1) : consomme le flux MQTT des
 * plateaux et délègue le traitement métier à `TraitementMesure`. Process
 * long, supervisé en prod (supervisor/horizon).
 */
class IngestionMesures extends Command
{
    /**
     * @var string
     */
    protected $signature = 'yagaz:ingest';

    /**
     * @var string
     */
    protected $description = 'Consomme le flux MQTT des plateaux (yagaz/v1/plateau/+/mesure) et range les mesures';

    /**
     * Topic auquel s'abonner (ADR 0003) : `+` capture l'uid du plateau.
     */
    private const string TOPIC_MESURE = 'yagaz/v1/plateau/+/mesure';

    public function handle(TraitementMesure $traitementMesure): int
    {
        $client = new MqttClient(
            host: (string) config('mqtt.host'),
            port: (int) config('mqtt.port'),
            clientId: (string) config('mqtt.client_id'),
        );

        $settings = (new ConnectionSettings)
            ->setUsername(config('mqtt.username'))
            ->setPassword(config('mqtt.password'))
            ->setKeepAliveInterval(60)
            ->setReconnectAutomatically(true)
            ->setMaxReconnectAttempts(0) // illimité : le worker doit tenir en continu.
            ->setDelayBetweenReconnectAttempts(2000);

        try {
            $client->connect($settings, true);

            $this->info("Connecté à {$client->getHost()}:{$client->getPort()}, abonnement à ".self::TOPIC_MESURE);

            $client->subscribe(
                self::TOPIC_MESURE,
                function (string $topic, string $payload) use ($traitementMesure): void {
                    $this->traiterMessage($traitementMesure, $topic, $payload);
                },
                MqttClient::QOS_AT_LEAST_ONCE,
            );

            $client->loop(true);
        } catch (Throwable $exception) {
            Log::error('yagaz:ingest — arrêt du worker suite à une erreur', [
                'exception' => $exception->getMessage(),
            ]);

            $this->error("Ingestion arrêtée : {$exception->getMessage()}");

            return self::FAILURE;
        } finally {
            try {
                if ($client->isConnected()) {
                    $client->disconnect();
                }
            } catch (MqttClientException) {
                // Rien à faire : la connexion est de toute façon perdue.
            }
        }

        return self::SUCCESS;
    }

    /**
     * Décode un message reçu sur `yagaz/v1/plateau/{uid}/mesure`, en extrait
     * l'`uid` du topic, et le passe au pipeline de traitement métier.
     */
    private function traiterMessage(TraitementMesure $traitementMesure, string $topic, string $payload): void
    {
        $uid = $this->extraireUidDuTopic($topic);

        if ($uid === null) {
            Log::warning('yagaz:ingest — topic inattendu, message ignoré', ['topic' => $topic]);

            return;
        }

        $donnees = json_decode($payload, associative: true);

        if (! is_array($donnees)) {
            Log::warning('yagaz:ingest — payload JSON invalide, message ignoré', [
                'topic' => $topic,
                'payload' => $payload,
            ]);

            return;
        }

        $message = array_merge($donnees, ['uid' => $uid]);

        try {
            $resultat = $traitementMesure->traiter($message);
            $this->journaliserResultat($resultat, $uid);
        } catch (Throwable $exception) {
            // Une mesure en échec ne doit jamais interrompre la consommation
            // du flux : on journalise et on continue.
            Log::error('yagaz:ingest — échec du traitement d\'un message', [
                'uid' => $uid,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function journaliserResultat(ResultatIngestionMesure $resultat, string $uid): void
    {
        $this->line("[{$uid}] {$resultat->statut->value}".($resultat->raison !== null ? " ({$resultat->raison})" : ''));
    }

    /**
     * Extrait `{uid}` du topic `yagaz/v1/plateau/{uid}/mesure` (ADR 0003).
     */
    private function extraireUidDuTopic(string $topic): ?string
    {
        if (preg_match('#^yagaz/v1/plateau/([^/]+)/mesure$#', $topic, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}

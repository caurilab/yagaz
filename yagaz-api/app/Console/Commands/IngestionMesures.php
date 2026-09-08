<?php

namespace App\Console\Commands;

use App\Services\Mesure\TraitementMesure;
use App\Services\Temperature\TraitementTemperature;
use App\Traits\TronqueLesLogs;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Exceptions\MqttClientException;
use PhpMqtt\Client\MqttClient;
use Throwable;

/**
 * Worker d'ingestion des mesures ET des températures de cuisine (doc 08 §1 ;
 * ADR 0011) : consomme le flux MQTT des plateaux et délègue le traitement
 * métier à `TraitementMesure`/`TraitementTemperature` selon le topic. Process
 * long, supervisé en prod (supervisor/horizon).
 */
class IngestionMesures extends Command
{
    use TronqueLesLogs;

    /**
     * @var string
     */
    protected $signature = 'yagaz:ingest';

    /**
     * @var string
     */
    protected $description = 'Consomme le flux MQTT des plateaux (mesure + température de cuisine) et range les données';

    /**
     * Topic auquel s'abonner (ADR 0003) : `+` capture l'uid du plateau.
     */
    private const string TOPIC_MESURE = 'yagaz/v1/plateau/+/mesure';

    /**
     * Topic de la température de cuisine (ADR 0011) : `+` capture l'uid du
     * plateau, comme `TOPIC_MESURE`.
     */
    private const string TOPIC_TEMPERATURE = 'yagaz/v1/plateau/+/temperature';

    public function handle(TraitementMesure $traitementMesure, TraitementTemperature $traitementTemperature): int
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

            $this->info("Connecté à {$client->getHost()}:{$client->getPort()}, abonnement à ".self::TOPIC_MESURE.' et '.self::TOPIC_TEMPERATURE);

            $client->subscribe(
                self::TOPIC_MESURE,
                function (string $topic, string $payload) use ($traitementMesure): void {
                    $this->traiterMessageMesure($traitementMesure, $topic, $payload);
                },
                MqttClient::QOS_AT_LEAST_ONCE,
            );

            $client->subscribe(
                self::TOPIC_TEMPERATURE,
                function (string $topic, string $payload) use ($traitementTemperature): void {
                    $this->traiterMessageTemperature($traitementTemperature, $topic, $payload);
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
     *
     * Isolation par message : une mesure en échec (exception) ne doit jamais
     * interrompre la consommation du flux — journalisée, puis on continue.
     */
    private function traiterMessageMesure(TraitementMesure $traitementMesure, string $topic, string $payload): void
    {
        $message = $this->decoderMessage($topic, $payload, 'mesure');

        if ($message === null) {
            return;
        }

        [$uid, $donnees] = $message;

        try {
            $resultat = $traitementMesure->traiter($donnees);
            $this->journaliserResultat($resultat->statut->value, $resultat->raison, $uid);
        } catch (Throwable $exception) {
            Log::error('yagaz:ingest — échec du traitement d\'une mesure', [
                'uid' => $uid,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Décode un message reçu sur `yagaz/v1/plateau/{uid}/temperature` (ADR
     * 0011), en extrait l'`uid` du topic, et le passe au pipeline de
     * traitement métier dédié.
     *
     * Isolation par message, comme `traiterMessageMesure()` : un message de
     * température toxique ne bloque jamais les autres.
     */
    private function traiterMessageTemperature(TraitementTemperature $traitementTemperature, string $topic, string $payload): void
    {
        $message = $this->decoderMessage($topic, $payload, 'temperature');

        if ($message === null) {
            return;
        }

        [$uid, $donnees] = $message;

        try {
            $resultat = $traitementTemperature->traiter($donnees);
            $this->journaliserResultat($resultat->statut->value, $resultat->raison, $uid);
        } catch (Throwable $exception) {
            Log::error('yagaz:ingest — échec du traitement d\'une température', [
                'uid' => $uid,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Décode un message MQTT commun aux deux flux : extrait l'`uid` du topic
     * (`yagaz/v1/plateau/{uid}/{suffixe}`) et parse le payload JSON. Retourne
     * `null` (en journalisant) si le topic est inattendu ou le payload
     * invalide — jamais d'exception, pour ne pas interrompre le worker.
     *
     * @return array{0: string, 1: array<string, mixed>}|null
     */
    private function decoderMessage(string $topic, string $payload, string $suffixe): ?array
    {
        $uid = $this->extraireUidDuTopic($topic, $suffixe);

        if ($uid === null) {
            Log::warning('yagaz:ingest — topic inattendu, message ignoré', [
                'topic' => $this->tronquerPourLog($topic),
            ]);

            return null;
        }

        $donnees = json_decode($payload, associative: true);

        if (! is_array($donnees)) {
            Log::warning('yagaz:ingest — payload JSON invalide, message ignoré', [
                'topic' => $this->tronquerPourLog($topic),
                'payload' => $this->tronquerPourLog($payload),
            ]);

            return null;
        }

        return [$uid, array_merge($donnees, ['uid' => $uid])];
    }

    private function journaliserResultat(string $statut, ?string $raison, string $uid): void
    {
        $this->line("[{$uid}] {$statut}".($raison !== null ? " ({$raison})" : ''));
    }

    /**
     * Extrait `{uid}` du topic `yagaz/v1/plateau/{uid}/{suffixe}` (ADR 0003,
     * étendu par l'ADR 0011 pour `temperature`).
     */
    private function extraireUidDuTopic(string $topic, string $suffixe): ?string
    {
        $motif = '#^yagaz/v1/plateau/([^/]+)/'.preg_quote($suffixe, '#').'$#';

        if (preg_match($motif, $topic, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}

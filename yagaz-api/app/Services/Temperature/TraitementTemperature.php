<?php

namespace App\Services\Temperature;

use App\Enums\CanalAlerte;
use App\Enums\StatutAlerte;
use App\Enums\StatutPlateau;
use App\Enums\TypeAlerte;
use App\Models\Alerte;
use App\Models\Plateau;
use App\Models\SessionCuisson;
use App\Models\Site;
use App\Models\Temperature;
use App\Services\Notification\Notificateur;
use App\Traits\TronqueLesLogs;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;

/**
 * Cœur du pipeline d'ingestion d'une température de cuisine (ADR 0011),
 * découplé du transport MQTT à l'image de `App\Services\Mesure\
 * TraitementMesure` : reçoit un message déjà décodé (JSON → tableau
 * associatif, `uid` extrait du topic) et applique successivement la
 * résolution/auth, la validation, la déduplication, le rangement, la
 * détection de cuisson et l'alerte de sécurité.
 *
 * Concurrence : mêmes hypothèses que `TraitementMesure::traiter()` — un
 * traitement séquentiel par plateau (un seul worker, ou une file sérialisée
 * par `plateau_id`). La déduplication par `seq` et la détection de cuisson
 * lisent puis écrivent l'état du site sans verrou explicite.
 */
final class TraitementTemperature
{
    use TronqueLesLogs;

    public function __construct(
        private readonly Notificateur $notificateur = new Notificateur,
    ) {}

    /**
     * Traite un message de température décodé.
     *
     * @param  array{uid: mixed, v?: mixed, ts?: mixed, temp_c?: mixed, seq?: mixed}  $message
     */
    public function traiter(array $message): ResultatIngestionTemperature
    {
        $uid = is_string($message['uid'] ?? null) ? $message['uid'] : null;

        $plateau = $uid !== null ? Plateau::where('uid', $uid)->first() : null;

        if (! $plateau instanceof Plateau || $plateau->statut !== StatutPlateau::Actif) {
            Log::warning('yagaz.ingestion_temperature: plateau inconnu ou non actif, message rejeté', [
                'uid' => is_string($message['uid'] ?? null) ? $this->tronquerPourLog($message['uid']) : null,
            ]);

            return ResultatIngestionTemperature::rejetee('plateau inconnu ou non actif');
        }

        // Le site est déduit du plateau (ADR 0011) : un plateau non posé
        // (en stock, jamais installé) n'a pas de site à qui rattacher la
        // détection de cuisson / l'alerte, le message est donc rejeté.
        if ($plateau->site_id === null) {
            Log::warning('yagaz.ingestion_temperature: plateau sans site associé, message rejeté', [
                'plateau_id' => $plateau->id,
            ]);

            return ResultatIngestionTemperature::rejetee('plateau sans site associé');
        }

        $raisonValidation = $this->validerMessage($message);

        if ($raisonValidation !== null) {
            Log::warning('yagaz.ingestion_temperature: message invalide, rejeté', [
                'plateau_id' => $plateau->id,
                'raison' => $raisonValidation,
                'message' => $this->tronquerPourLog((string) json_encode($message)),
            ]);

            return ResultatIngestionTemperature::rejetee($raisonValidation);
        }

        $seq = (int) $message['seq'];

        if ($this->estDoublon($plateau, $seq)) {
            return ResultatIngestionTemperature::doublon("seq {$seq} déjà rangé");
        }

        $tempC = (float) $message['temp_c'];
        $mesureAt = $this->resoudreMesureAt((int) $message['ts'], $plateau);

        try {
            $temperature = Temperature::create([
                'plateau_id' => $plateau->id,
                'site_id' => $plateau->site_id,
                'mesure_at' => $mesureAt,
                'recu_at' => now(),
                'temp_c' => $tempC,
                'seq' => $seq,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Filet dur contre l'insertion en double exacte, comme
            // `TraitementMesure::traiter()` : la contrainte d'unicité
            // (plateau_id, mesure_at, seq) rattrape ici un doublon rare que
            // la dédup par fenêtre récente aurait laissé passer.
            return ResultatIngestionTemperature::doublon("seq {$seq} déjà rangé (contrainte d'unicité)");
        }

        $plateau->dernier_vu_at = now();
        $plateau->save();

        /** @var Site $site */
        $site = $plateau->site ?? Site::findOrFail($plateau->site_id);

        [$sessionCuisson, $cuissonEnCours] = $this->traiterCuisson($site, $tempC, $mesureAt);

        $alerte = $this->gererAlerteDanger($site, $tempC);

        return ResultatIngestionTemperature::rangee($temperature, $cuissonEnCours, $sessionCuisson, $alerte);
    }

    /**
     * Vérifie la présence et le typage des champs requis (ADR 0011,
     * extension ADR 0003), l'absence d'aberration physique sur `temp_c`
     * (plage plausible d'une cuisine, [-10, 300] °C — capteur ambiant, pas la
     * température interne de l'électronique), et le bornage de `seq` et `ts`
     * à une plage plausible (mêmes bornes que `TraitementMesure`).
     */
    private function validerMessage(array $message): ?string
    {
        foreach (['v', 'ts', 'temp_c', 'seq'] as $champ) {
            if (! array_key_exists($champ, $message) || ! is_numeric($message[$champ])) {
                return "champ requis manquant ou invalide : {$champ}";
            }
        }

        $tempC = (float) $message['temp_c'];

        if ($tempC < -10 || $tempC > 300) {
            return "temp_c aberrant : {$tempC}";
        }

        $seq = (float) $message['seq'];

        if ($seq < 0 || $seq > (float) config('mesure.seq_max')) {
            return "seq hors plage : {$seq}";
        }

        $ts = (float) $message['ts'];
        $tsMin = CarbonImmutable::parse((string) config('mesure.ts_min'), 'UTC')->timestamp;
        $tsMax = CarbonImmutable::now()->addDay()->timestamp;

        if ($ts < $tsMin || $ts > $tsMax) {
            return "ts hors plage plausible : {$ts}";
        }

        return null;
    }

    /**
     * Une température est un doublon si une température de même couple
     * `(plateau_id, seq)` existe déjà dans la fenêtre récente
     * `mesure.dedup_fenetre_heures` (même logique que `TraitementMesure::
     * estDoublon()`). Le filet dur contre le doublon exact reste la clé
     * primaire composite de la table `temperatures`, rattrapée dans
     * `traiter()`.
     */
    private function estDoublon(Plateau $plateau, int $seq): bool
    {
        $fenetre = now()->subHours((int) config('mesure.dedup_fenetre_heures'));

        return Temperature::where('plateau_id', $plateau->id)
            ->where('seq', $seq)
            ->where('mesure_at', '>=', $fenetre)
            ->exists();
    }

    /**
     * Borne `mesure_at` à ±1h autour de l'heure serveur, comme
     * `TraitementMesure::resoudreMesureAt()`.
     */
    private function resoudreMesureAt(int $ts, Plateau $plateau): CarbonImmutable
    {
        $mesureAt = CarbonImmutable::createFromTimestampUTC($ts);
        $now = CarbonImmutable::now();
        $borneBasse = $now->subHour();
        $borneHaute = $now->addHour();

        if ($mesureAt->lt($borneBasse) || $mesureAt->gt($borneHaute)) {
            Log::warning('yagaz.ingestion_temperature: dérive d\'horloge détectée, mesure_at bornée', [
                'plateau_id' => $plateau->id,
                'ts' => $ts,
            ]);

            return $mesureAt->lt($borneBasse) ? $borneBasse : $borneHaute;
        }

        return $mesureAt;
    }

    /**
     * Détection de cuisson (ADR 0011) : ouvre une session au franchissement
     * du seuil `seuil_cuisson_c` vers le haut quand aucune session n'est
     * ouverte pour le site, maintient `temp_max_c` (maximum observé) tant que
     * le seuil reste franchi, et ferme la session dès que la température
     * repasse sous le seuil.
     *
     * **Règle anti-rebond retenue (v1)** : on ferme dès la PREMIÈRE lecture
     * sous le seuil — une baisse « constatée » suffit, sans temporiser
     * d'abord une fenêtre `duree_min_cuisson_s` avant de fermer. Ce choix
     * privilégie la réactivité de l'affichage (« cuisson en cours » ne doit
     * pas s'attarder après extinction du gaz) au prix d'un éventuel rebond
     * bref qui rouvrirait une nouvelle session si la température remonte
     * aussitôt — accepté en v1 ; la constante `duree_min_cuisson_s` reste en
     * config pour un lissage temporel futur si le matériel réel montre un
     * bruit de mesure justifiant un anti-rebond plus strict.
     *
     * @return array{0: ?SessionCuisson, 1: bool} La session concernée (ou
     *                                            `null` si aucune n'a jamais
     *                                            été ouverte) et si une
     *                                            cuisson est en cours.
     */
    private function traiterCuisson(Site $site, float $tempC, CarbonImmutable $mesureAt): array
    {
        $seuilCuisson = (float) config('mesure.seuil_cuisson_c');

        $sessionOuverte = SessionCuisson::where('site_id', $site->id)
            ->whereNull('fin_at')
            ->latest('debut_at')
            ->first();

        if ($tempC >= $seuilCuisson) {
            if ($sessionOuverte === null) {
                $sessionOuverte = SessionCuisson::create([
                    'site_id' => $site->id,
                    'debut_at' => $mesureAt,
                    'temp_max_c' => $tempC,
                ]);
            } elseif ($tempC > $sessionOuverte->temp_max_c) {
                $sessionOuverte->temp_max_c = $tempC;
                $sessionOuverte->save();
            }

            return [$sessionOuverte, true];
        }

        if ($sessionOuverte !== null) {
            $sessionOuverte->fin_at = $mesureAt;
            $sessionOuverte->save();
        }

        return [$sessionOuverte, false];
    }

    /**
     * Alerte sécurité (ADR 0011) : au-delà de `seuil_danger_c`, adresse le
     * propriétaire du site (via `Notificateur`, comme `gererAlerteSeuilBas()`
     * de `TraitementMesure`) — anti-spam tant qu'une alerte
     * `temperature_elevee` non résolue existe déjà pour ce site.
     *
     * Redescente sous le seuil danger : résout automatiquement toute alerte
     * `temperature_elevee` non résolue du site (`resoudreAlerteDangerSi
     * Redescendue()`), pour qu'une remontée ultérieure au-dessus du seuil
     * puisse émettre une nouvelle alerte — c'est la deuxième moitié de la
     * règle anti-spam de l'ADR (« … / que la température n'est pas
     * redescendue »).
     */
    private function gererAlerteDanger(Site $site, float $tempC): ?Alerte
    {
        $seuilDanger = (float) config('mesure.seuil_danger_c');

        if ($tempC < $seuilDanger) {
            $this->resoudreAlerteDangerSiRedescendue($site);

            return null;
        }

        $alerteNonResolue = Alerte::where('site_id', $site->id)
            ->where('type', TypeAlerte::TemperatureElevee)
            ->where('statut', '!=', StatutAlerte::Resolue)
            ->exists();

        if ($alerteNonResolue) {
            return null;
        }

        $proprietaire = $this->notificateur->proprietaireDuSite($site);

        if ($proprietaire !== null) {
            return $this->notificateur->notifier(
                $proprietaire,
                TypeAlerte::TemperatureElevee,
                ['temp_c' => $tempC],
                site: $site,
            );
        }

        // Foyer sans propriétaire enregistré (cas de test notamment) :
        // l'alerte reste créée sans destinataire précis, comme
        // `TraitementMesure::gererAlerteSeuilBas()`.
        return Alerte::create([
            'site_id' => $site->id,
            'type' => TypeAlerte::TemperatureElevee,
            'statut' => StatutAlerte::Emise,
            'canal' => CanalAlerte::Push,
        ]);
    }

    /**
     * Marque résolue toute alerte `temperature_elevee` non résolue du site
     * dès que la température redescend sous `seuil_danger_c` (anti-spam,
     * ADR 0011) : la prochaine remontée au-dessus du seuil pourra donc
     * émettre une nouvelle alerte plutôt que de rester bloquée par
     * l'ancienne, jamais résolue manuellement.
     */
    private function resoudreAlerteDangerSiRedescendue(Site $site): void
    {
        Alerte::where('site_id', $site->id)
            ->where('type', TypeAlerte::TemperatureElevee)
            ->where('statut', '!=', StatutAlerte::Resolue)
            ->update(['statut' => StatutAlerte::Resolue->value]);
    }
}

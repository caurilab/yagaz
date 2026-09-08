<?php

namespace App\Services\Mesure;

use App\Enums\CanalAlerte;
use App\Enums\RoleBouteille;
use App\Enums\StatutAlerte;
use App\Enums\StatutPlateau;
use App\Enums\TypeAlerte;
use App\Models\Alerte;
use App\Models\Bouteille;
use App\Models\Mesure;
use App\Models\NiveauCourant;
use App\Models\Plateau;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Cœur du pipeline d'ingestion d'une mesure (doc 08 §3-8), découplé du
 * transport MQTT : reçoit un message déjà décodé (JSON → tableau associatif,
 * `uid` extrait du topic) et applique successivement la résolution/auth, la
 * validation, la déduplication, le rangement, le lissage, le calibrage de la
 * tare, le calcul du niveau/autonomie, et le déclenchement d'alerte.
 */
final class TraitementMesure
{
    /**
     * Nombre de mesures brutes (les plus récentes) utilisées pour la moyenne
     * mobile exponentielle qui lisse le poids affiché (doc 08 §4).
     */
    private const int TAILLE_FENETRE_LISSAGE = 5;

    public function __construct(
        private readonly TareCalibrage $tareCalibrage = new TareCalibrage,
        private readonly Autonomie $autonomie = new Autonomie,
    ) {}

    /**
     * Traite un message de mesure décodé.
     *
     * @param  array{uid: mixed, v?: mixed, ts?: mixed, poids_g?: mixed, seq?: mixed, batt_mv?: mixed, rssi?: mixed, temp_c?: mixed}  $message
     */
    public function traiter(array $message): ResultatIngestionMesure
    {
        $uid = is_string($message['uid'] ?? null) ? $message['uid'] : null;

        $plateau = $uid !== null ? Plateau::where('uid', $uid)->first() : null;

        if (! $plateau instanceof Plateau || $plateau->statut !== StatutPlateau::Actif) {
            Log::warning('yagaz.ingestion: plateau inconnu ou non actif, message rejeté', [
                'uid' => $message['uid'] ?? null,
            ]);

            return ResultatIngestionMesure::rejetee('plateau inconnu ou non actif');
        }

        $raisonValidation = $this->validerMessage($message);

        if ($raisonValidation !== null) {
            Log::warning('yagaz.ingestion: message invalide, rejeté', [
                'plateau_id' => $plateau->id,
                'raison' => $raisonValidation,
                'message' => $message,
            ]);

            return ResultatIngestionMesure::rejetee($raisonValidation);
        }

        $seq = (int) $message['seq'];

        if ($this->estDoublon($plateau, $seq)) {
            return ResultatIngestionMesure::doublon("seq {$seq} déjà rangé ou en régression");
        }

        $poidsG = (int) $message['poids_g'];
        $mesureAt = $this->resoudreMesureAt((int) $message['ts'], $plateau);
        $bouteille = Bouteille::where('plateau_id', $plateau->id)->first();
        $sansBouteille = $poidsG < (int) config('mesure.seuil_plateau_nu');

        $tareConnue = $bouteille?->tare_g;
        $gazBrut = $tareConnue !== null ? max(0, $poidsG - $tareConnue) : null;

        $mesure = Mesure::create([
            'plateau_id' => $plateau->id,
            'bouteille_id' => $bouteille?->id,
            'mesure_at' => $mesureAt,
            'recu_at' => now(),
            'poids_g' => $poidsG,
            'gaz_g' => $gazBrut,
            'seq' => $seq,
            'batt_mv' => $message['batt_mv'] ?? null,
            'rssi' => $message['rssi'] ?? null,
            'temp_c' => $message['temp_c'] ?? null,
        ]);

        $plateau->dernier_vu_at = now();
        $plateau->save();

        if (! $bouteille instanceof Bouteille || $sansBouteille) {
            return ResultatIngestionMesure::rangee($mesure);
        }

        $poidsLisse = $this->calculerPoidsLisse($bouteille, $poidsG);

        $tareNominale = $bouteille->format?->tare_nominale_g ?? $poidsLisse;
        $this->tareCalibrage->affiner($bouteille, $poidsLisse, (int) $tareNominale);
        $bouteille->refresh();

        [$niveauCourant, $alerte] = $this->recalculerNiveauEtAlerte($bouteille, $poidsLisse);

        return ResultatIngestionMesure::rangee($mesure, $niveauCourant, $alerte);
    }

    /**
     * Vérifie la présence et le typage des champs requis (ADR 0003) et
     * l'absence d'aberration physique sur le poids (doc 08 §3). Retourne la
     * raison de rejet, ou null si le message est valide.
     */
    private function validerMessage(array $message): ?string
    {
        foreach (['v', 'ts', 'poids_g', 'seq'] as $champ) {
            if (! array_key_exists($champ, $message) || ! is_numeric($message[$champ])) {
                return "champ requis manquant ou invalide : {$champ}";
            }
        }

        $poidsG = (float) $message['poids_g'];

        if ($poidsG < 0 || $poidsG > (float) config('mesure.poids_max_absolu')) {
            return "poids_g aberrant : {$poidsG}";
        }

        return null;
    }

    /**
     * `seq` déjà rangé pour ce plateau, ou en régression par rapport au
     * dernier `seq` connu (doc 08 §3).
     */
    private function estDoublon(Plateau $plateau, int $seq): bool
    {
        $dernierSeq = Mesure::where('plateau_id', $plateau->id)->max('seq');

        return $dernierSeq !== null && $seq <= $dernierSeq;
    }

    /**
     * Borne `mesure_at` à ±1h autour de l'heure serveur (doc 08 §3) ; une
     * dérive est journalisée mais ne bloque pas le rangement (`recu_at`
     * conserve l'heure serveur exacte, gérée séparément).
     */
    private function resoudreMesureAt(int $ts, Plateau $plateau): CarbonImmutable
    {
        $mesureAt = CarbonImmutable::createFromTimestampUTC($ts);
        $now = CarbonImmutable::now();
        $borneBasse = $now->subHour();
        $borneHaute = $now->addHour();

        if ($mesureAt->lt($borneBasse) || $mesureAt->gt($borneHaute)) {
            Log::warning('yagaz.ingestion: dérive d\'horloge détectée, mesure_at bornée', [
                'plateau_id' => $plateau->id,
                'ts' => $ts,
            ]);

            return $mesureAt->lt($borneBasse) ? $borneBasse : $borneHaute;
        }

        return $mesureAt;
    }

    /**
     * Moyenne mobile exponentielle sur les `TAILLE_FENETRE_LISSAGE` dernières
     * mesures brutes de la bouteille (la plus ancienne d'abord), la mesure
     * qui vient d'être rangée incluse (doc 08 §4). Le poids brut reste rangé
     * tel quel ; seul ce poids lissé sert au niveau et à l'autonomie.
     */
    private function calculerPoidsLisse(Bouteille $bouteille, int $poidsGActuel): float
    {
        $historique = Mesure::where('bouteille_id', $bouteille->id)
            ->orderByDesc('mesure_at')
            ->orderByDesc('seq')
            ->limit(self::TAILLE_FENETRE_LISSAGE)
            ->pluck('poids_g')
            ->reverse()
            ->values();

        $alpha = 2 / (self::TAILLE_FENETRE_LISSAGE + 1);
        $ema = (float) $historique->first(default: $poidsGActuel);

        foreach ($historique->slice(1) as $poids) {
            $ema = $alpha * $poids + (1 - $alpha) * $ema;
        }

        return $ema;
    }

    /**
     * Calcule gaz_g/niveau_pct/autonomie à partir du poids lissé (doc 08
     * §6-7), met à jour `niveaux_courants`, et déclenche l'alerte de seuil
     * bas si elle est due (doc 08 §8).
     *
     * @return array{0: NiveauCourant, 1: ?Alerte}
     */
    private function recalculerNiveauEtAlerte(Bouteille $bouteille, float $poidsLisse): array
    {
        $format = $bouteille->format;
        $tareG = $bouteille->tare_g ?? $format?->tare_nominale_g ?? 0;
        $contenanceGazG = $format?->contenance_gaz_g ?? 1;

        $gazG = max(0, (int) round($poidsLisse - $tareG));
        $niveauPct = (int) max(0, min(100, round($gazG / max(1, $contenanceGazG) * 100)));

        $debitGParH = $this->autonomie->estimerDebit($bouteille);
        $autonomieMin = $this->autonomie->autonomieMinutes($gazG, $debitGParH);

        $niveauAvant = NiveauCourant::find($bouteille->id)?->niveau_pct;

        $niveauCourant = NiveauCourant::updateOrCreate(
            ['bouteille_id' => $bouteille->id],
            [
                'gaz_g' => $gazG,
                'niveau_pct' => $niveauPct,
                'autonomie_min' => $autonomieMin,
                'debit_g_par_h' => $debitGParH,
                'calcule_at' => now(),
            ]
        );

        $alerte = $this->gererAlerteSeuilBas($bouteille, $niveauAvant, $niveauPct);

        return [$niveauCourant, $alerte];
    }

    /**
     * Émet une alerte `seuil_bas` si le niveau d'une bouteille **active**
     * vient de franchir son seuil à la baisse, sauf s'il en existe déjà une
     * non résolue (anti-spam, doc 08 §8). Les bouteilles de secours ne
     * déclenchent jamais cette alerte (sobriété, doc 08 §8).
     */
    private function gererAlerteSeuilBas(Bouteille $bouteille, ?int $niveauAvant, int $niveauApres): ?Alerte
    {
        if ($bouteille->role_bouteille !== RoleBouteille::Active) {
            return null;
        }

        $seuil = $bouteille->seuil_bas_pct;
        $vientDeFranchir = $niveauApres < $seuil && ($niveauAvant === null || $niveauAvant >= $seuil);

        if (! $vientDeFranchir) {
            return null;
        }

        $alerteNonResolue = Alerte::where('bouteille_id', $bouteille->id)
            ->where('type', TypeAlerte::SeuilBas)
            ->where('statut', '!=', StatutAlerte::Resolue)
            ->exists();

        if ($alerteNonResolue) {
            return null;
        }

        return Alerte::create([
            'bouteille_id' => $bouteille->id,
            'type' => TypeAlerte::SeuilBas,
            'statut' => StatutAlerte::Emise,
            'canal' => CanalAlerte::Push,
        ]);
    }
}

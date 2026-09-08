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
use App\Models\Site;
use App\Services\Notification\Notificateur;
use App\Traits\TronqueLesLogs;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;

/**
 * Cœur du pipeline d'ingestion d'une mesure (doc 08 §3-8), découplé du
 * transport MQTT : reçoit un message déjà décodé (JSON → tableau associatif,
 * `uid` extrait du topic) et applique successivement la résolution/auth, la
 * validation, la déduplication, le rangement, le lissage, le calibrage de la
 * tare, le calcul du niveau/autonomie, et le déclenchement d'alerte.
 *
 * Concurrence (audit sécurité, correctif #5) : `traiter()` suppose un
 * traitement **séquentiel par plateau** — un seul worker consommant le flux
 * MQTT, ou à défaut une file sérialisée par `plateau_id`. La déduplication
 * par `seq` (cf. `estDoublon()`) et le calibrage incrémental de la tare (cf.
 * `TareCalibrage`) lisent puis écrivent l'état d'un plateau/bouteille sans
 * verrou explicite : deux appels concurrents pour le même plateau peuvent
 * produire une lecture obsolète (double rangement, calibrage incohérent). En
 * scale-out (plusieurs workers), entourer l'appel d'un verrou ou d'une
 * transaction par `plateau_id` (ex. `Cache::lock("plateau:{$id}")` ou
 * `SELECT … FOR UPDATE`) avant d'invoquer `traiter()`.
 */
final class TraitementMesure
{
    use TronqueLesLogs;

    /**
     * Nombre de mesures brutes (les plus récentes) utilisées pour la moyenne
     * mobile exponentielle qui lisse le poids affiché (doc 08 §4).
     */
    private const int TAILLE_FENETRE_LISSAGE = 5;

    public function __construct(
        private readonly TareCalibrage $tareCalibrage = new TareCalibrage,
        private readonly Autonomie $autonomie = new Autonomie,
        private readonly Notificateur $notificateur = new Notificateur,
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
                'uid' => is_string($message['uid'] ?? null) ? $this->tronquerPourLog($message['uid']) : null,
            ]);

            return ResultatIngestionMesure::rejetee('plateau inconnu ou non actif');
        }

        $raisonValidation = $this->validerMessage($message);

        if ($raisonValidation !== null) {
            Log::warning('yagaz.ingestion: message invalide, rejeté', [
                'plateau_id' => $plateau->id,
                'raison' => $raisonValidation,
                'message' => $this->tronquerPourLog((string) json_encode($message)),
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

        try {
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
        } catch (UniqueConstraintViolationException) {
            // Filet dur contre l'insertion en double exacte (même
            // plateau_id, mesure_at, seq) : la dédup par fenêtre récente
            // (cf. estDoublon()) a pu laisser passer un doublon rare (fenêtre
            // expirée, course entre deux traitements…) ; la contrainte
            // d'unicité de la table le rattrape ici sans remonter d'erreur
            // (audit sécurité, correctif #2).
            return ResultatIngestionMesure::doublon("seq {$seq} déjà rangé (contrainte d'unicité)");
        }

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
     * Vérifie la présence et le typage des champs requis (ADR 0003), l'absence
     * d'aberration physique sur le poids (doc 08 §3), et le bornage de `seq`
     * et `ts` à une plage plausible (audit sécurité, correctif #4 — distinct
     * du bornage de dérive d'horloge de `resoudreMesureAt()`, qui recale les
     * petites dérives sans rejeter). Retourne la raison de rejet, ou null si
     * le message est valide.
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
     * Une mesure est un doublon si une mesure de même couple
     * `(plateau_id, seq)` existe déjà dans la fenêtre récente
     * `mesure.dedup_fenetre_heures` (audit sécurité, correctif #2). On ne se
     * base plus sur `max(seq)` du plateau : un plateau qui redémarre repart
     * avec un `seq` bas (nouveau `mesure_at`), et doit être accepté plutôt
     * que rejeté en bloc comme régression. Le filet dur contre le doublon
     * exact (même plateau_id, mesure_at, seq) reste la clé primaire composite
     * de la table `mesures`, rattrapée dans `traiter()`.
     *
     * Un saut de `seq` vers le haut est détecté et journalisé à titre
     * purement informatif (trou de séquence) ; il ne provoque aucun rejet.
     */
    private function estDoublon(Plateau $plateau, int $seq): bool
    {
        $fenetre = now()->subHours((int) config('mesure.dedup_fenetre_heures'));

        $dejaRangeDansLaFenetre = Mesure::where('plateau_id', $plateau->id)
            ->where('seq', $seq)
            ->where('mesure_at', '>=', $fenetre)
            ->exists();

        if ($dejaRangeDansLaFenetre) {
            return true;
        }

        $this->detecterTrouSeq($plateau, $seq);

        return false;
    }

    /**
     * Journalise, à titre purement informatif, un saut de `seq` vers le haut
     * par rapport au dernier `seq` connu du plateau (trou de séquence
     * possible — messages perdus). Ne rejette jamais la mesure (doc 08 §3,
     * audit sécurité correctif #2).
     */
    private function detecterTrouSeq(Plateau $plateau, int $seq): void
    {
        $dernierSeq = Mesure::where('plateau_id', $plateau->id)->max('seq');

        if ($dernierSeq !== null && $seq > $dernierSeq + 1) {
            Log::info('yagaz.ingestion: saut de seq détecté (informatif, trou possible)', [
                'plateau_id' => $plateau->id,
                'seq_precedent' => $dernierSeq,
                'seq_recu' => $seq,
            ]);
        }
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

        $alerte = $this->gererAlerteSeuilBas($bouteille, $niveauAvant, $niveauPct, $poidsLisse);

        return [$niveauCourant, $alerte];
    }

    /**
     * Émet une alerte `seuil_bas` pour une bouteille **active** dans deux cas
     * (doc 08 §8, renforcé par l'audit sécurité correctif #1), sauf s'il en
     * existe déjà une non résolue (anti-spam) :
     * 1. le niveau calculé (`niveau_pct`, dépendant de la tare calibrée)
     *    vient de franchir le seuil à la baisse ;
     * 2. **indépendamment de la tare et du niveau calculé**, le poids brut
     *    lissé approche le poids à vide nominal du format
     *    (`tare_nominale_g + securite_marge_plancher_g`) — ce filet de
     *    sécurité absolu détecte une bouteille physiquement quasi vide même
     *    si la tare calibrée est faussée basse et masque le franchissement
     *    de seuil via `niveau_pct`.
     *
     * Les bouteilles de secours ne déclenchent jamais cette alerte (sobriété,
     * doc 08 §8).
     *
     * Depuis la Phase 5 (contrat API doc 11, §3), l'alerte est adressée au
     * propriétaire du site quand il est identifiable (`Notificateur`, qui la
     * route aussi vers son(ses) canal(aux) préféré(s)) ; sinon elle reste
     * créée sans destinataire précis, comme avant (foyer sans propriétaire
     * enregistré — cas de test notamment).
     *
     * ADR 0009 (maillon A) : si le site a un livreur habituel actif, ce
     * dernier est AUSSI notifié — jamais à la place du foyer — avec l'info
     * minimale de l'ADR 0008 (`Notificateur::notifierLivreurHabituel()`).
     * Purement automatique : aucune commande n'est créée à ce stade.
     */
    private function gererAlerteSeuilBas(Bouteille $bouteille, ?int $niveauAvant, int $niveauApres, float $poidsLisse): ?Alerte
    {
        if ($bouteille->role_bouteille !== RoleBouteille::Active) {
            return null;
        }

        $seuil = $bouteille->seuil_bas_pct;
        $vientDeFranchirParNiveau = $niveauApres < $seuil && ($niveauAvant === null || $niveauAvant >= $seuil);

        if (! $vientDeFranchirParNiveau && ! $this->estPresDuPoidsAVide($bouteille, $poidsLisse)) {
            return null;
        }

        $alerteNonResolue = Alerte::where('bouteille_id', $bouteille->id)
            ->where('type', TypeAlerte::SeuilBas)
            ->where('statut', '!=', StatutAlerte::Resolue)
            ->exists();

        if ($alerteNonResolue) {
            return null;
        }

        $site = $bouteille->site;

        $this->notifierLivreurHabituelSiDefini($site, $bouteille);

        $proprietaire = $this->notificateur->proprietaireDuSite($site);

        if ($proprietaire !== null) {
            return $this->notificateur->notifier(
                $proprietaire,
                TypeAlerte::SeuilBas,
                ['bouteille_uuid' => $bouteille->uuid],
                bouteille: $bouteille,
                site: $site,
            );
        }

        return Alerte::create([
            'bouteille_id' => $bouteille->id,
            'type' => TypeAlerte::SeuilBas,
            'statut' => StatutAlerte::Emise,
            'canal' => CanalAlerte::Push,
        ]);
    }

    /**
     * Notifie le livreur habituel du site, s'il est défini et actif (ADR
     * 0009, maillon A), avec l'information minimale de l'ADR 0008: nom
     * d'affichage du site, zone, format de la bouteille concernée — jamais
     * le niveau exact, l'autonomie, ni l'historique. N'échoue jamais faute
     * de livreur habituel (rien à notifier) ni de livreur désigné (compte
     * potentiellement supprimé) — le foyer reste notifié séparément.
     */
    private function notifierLivreurHabituelSiDefini(?Site $site, Bouteille $bouteille): void
    {
        $livreurHabituel = $site?->livreurHabituel;

        if ($livreurHabituel === null || ! $livreurHabituel->actif) {
            return;
        }

        $livreur = $livreurHabituel->livreur;

        if ($livreur === null) {
            return;
        }

        $this->notificateur->notifierLivreurHabituel($livreur, $site, [
            'site_nom' => $site->nom,
            'zone' => $site->zone,
            'format_code' => $bouteille->format?->code,
        ]);
    }

    /**
     * Filet de sécurité absolu (audit sécurité, correctif #1) : vrai si le
     * poids brut lissé est à `tare_nominale_g + securite_marge_plancher_g` ou
     * en dessous. Ne dépend jamais de `tare_g` : une tare calibrée faussée
     * basse ne peut donc pas masquer une bouteille physiquement quasi vide.
     */
    private function estPresDuPoidsAVide(Bouteille $bouteille, float $poidsLisse): bool
    {
        $tareNominale = $bouteille->format?->tare_nominale_g;

        if ($tareNominale === null) {
            return false;
        }

        $margePlancher = (int) config('mesure.securite_marge_plancher_g');

        return $poidsLisse <= $tareNominale + $margePlancher;
    }
}

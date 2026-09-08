<?php

namespace App\Services\Temperature;

use App\Models\SessionCuisson;
use App\Models\Site;
use App\Models\Temperature;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Analyses température/cuisson d'un site (ADR 0011) : courbe horaire de
 * température, pic observé, histogramme des cuissons par heure de la
 * journée, période de la journée dominante, fréquence de cuisson et série
 * journalière — pour répondre à « quand fait-il le plus chaud »/« à quelle
 * heure cuisine-t-on » (endpoint dédié, `GET
 * /api/sites/{uuid}/temperature/analyse`).
 *
 * Agrégation portable SQLite/PostgreSQL, même approche que
 * `AgregationRegionale`/`AgregationAnalyse` : les regroupements par heure/jour
 * sont faits en PHP à partir de `temperatures`/`sessions_cuisson` chargées une
 * fois sur la période, jamais par une expression de date propre à un moteur
 * (`date_trunc`/`strftime`). Périmètre déjà cloisonné par l'appelant
 * (`SitePolicy::view`, 404 hors périmètre, comme `TemperatureController::show()`).
 *
 * Choix documenté : ce calcul reste dans un endpoint dédié plutôt que dans
 * `AgregationAnalyse`/`GET /analyse` — les métriques ici sont propres à UN
 * site (une cuisine physique, un capteur), alors que `AgregationAnalyse`
 * agrège potentiellement plusieurs sites du foyer ; les mélanger aurait
 * rendu `courbe_horaire`/`pic_temperature` ambigus (plusieurs cuisines).
 */
final class AgregationTemperature
{
    private const int LIMITE_LIGNES = 10_000;

    /**
     * @return array<string, mixed>
     */
    public function analyser(Site $site, string $periode): array
    {
        [$debut, $fin] = $this->bornesPeriode($periode);

        $temperatures = Temperature::where('site_id', $site->id)
            ->whereBetween('mesure_at', [$debut, $fin])
            ->orderBy('mesure_at')
            ->limit(self::LIMITE_LIGNES)
            ->get(['mesure_at', 'temp_c']);

        // Sessions dont le début tombe dans la période (voir
        // `cuissonParHeure()` pour la justification de ce choix, plus étroit
        // que `AgregationAnalyse::cuisson()` qui inclut aussi les sessions à
        // cheval sur la période — ici on veut strictement « les cuissons
        // démarrées cette heure-ci », pas une part d'une cuisson démarrée la
        // veille).
        $sessions = SessionCuisson::where('site_id', $site->id)
            ->whereBetween('debut_at', [$debut, $fin])
            ->limit(self::LIMITE_LIGNES)
            ->get(['debut_at', 'fin_at']);

        $cuissonParHeure = $this->cuissonParHeure($sessions);
        $heurePointeCuisson = $this->heurePointeCuisson($cuissonParHeure);

        return [
            'periode' => $periode,
            'debut' => $debut->toIso8601String(),
            'fin' => $fin->toIso8601String(),
            'courbe_horaire' => $this->courbeHoraire($temperatures),
            'pic_temperature' => $this->picTemperature($temperatures),
            'cuisson_par_heure' => $cuissonParHeure,
            'heure_pointe_cuisson' => $heurePointeCuisson,
            'periode_dominante' => $heurePointeCuisson !== null ? $this->periodeDominante($heurePointeCuisson) : null,
            'frequence' => $this->frequence($sessions, $debut, $fin),
            'serie_journaliere' => $this->serieJournaliere($temperatures, $sessions),
            'nb_mesures' => $temperatures->count(),
        ];
    }

    /**
     * Bornes [début, fin] de la période courante (jour, semaine ISO, mois
     * civil) — mêmes bornes que `AgregationAnalyse::bornesPeriode()`, sans
     * l'option « année » (hors périmètre de cet endpoint).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function bornesPeriode(string $periode): array
    {
        $maintenant = CarbonImmutable::now();

        return match ($periode) {
            'jour' => [$maintenant->startOfDay(), $maintenant->endOfDay()],
            'semaine' => [$maintenant->startOfWeek(), $maintenant->endOfWeek()],
            default => [$maintenant->startOfMonth(), $maintenant->endOfMonth()],
        };
    }

    /**
     * Température moyenne par heure de la journée (0..23), toutes dates
     * confondues sur la période — pour repérer quand il fait le plus chaud
     * dans la journée type. 24 points toujours renvoyés (fiabilité de
     * l'affichage même avec peu de données), `temp_moyenne_c` à `null` pour
     * une heure sans aucun relevé sur la période (fallback documenté :
     * aucune interpolation tentée).
     *
     * @param  Collection<int, Temperature>  $temperatures
     * @return array<int, array{heure: int, temp_moyenne_c: ?float}>
     */
    private function courbeHoraire(Collection $temperatures): array
    {
        $parHeure = $temperatures->groupBy(fn (Temperature $t) => $t->mesure_at->hour);

        $courbe = [];

        for ($heure = 0; $heure < 24; $heure++) {
            $releves = $parHeure->get($heure);

            $courbe[] = [
                'heure' => $heure,
                'temp_moyenne_c' => $releves !== null && $releves->isNotEmpty()
                    ? round($releves->avg('temp_c'), 2)
                    : null,
            ];
        }

        return $courbe;
    }

    /**
     * Température maximale observée sur la période, et l'heure de la journée
     * à laquelle elle a été relevée — proxy de « l'heure typique du pic »,
     * faute d'historique suffisant pour une vraie moyenne des pics
     * quotidiens (fallback documenté). `null` si aucun relevé sur la période.
     *
     * @param  Collection<int, Temperature>  $temperatures
     * @return array{heure: ?int, temp_c: ?float}
     */
    private function picTemperature(Collection $temperatures): array
    {
        $pic = $temperatures->sortByDesc('temp_c')->first();

        return [
            'heure' => $pic?->mesure_at->hour,
            'temp_c' => $pic?->temp_c,
        ];
    }

    /**
     * Histogramme des cuissons par heure de la journée (0..23) : nombre de
     * sessions dont le `debut_at` tombe dans cette heure, et durée cumulée
     * (minutes) de ces sessions — « on cuisine surtout à telle heure ».
     *
     * Durée non bornée à la fin de période (contrairement à
     * `AgregationAnalyse::cuisson()`) : chaque session comptée ici a déjà son
     * `debut_at` dans la période, sa durée propre est donc la métrique la
     * plus lisible pour ce bucket horaire, même si `fin_at` déborde
     * légèrement la fin de période. Une session encore ouverte (`fin_at`
     * `null`) est comptée jusqu'à `now()` (`dureeMinutes()`).
     *
     * @param  Collection<int, SessionCuisson>  $sessions
     * @return array<int, array{heure: int, sessions: int, duree_min: int}>
     */
    private function cuissonParHeure(Collection $sessions): array
    {
        $parHeure = $sessions->groupBy(fn (SessionCuisson $s) => $s->debut_at->hour);

        $histogramme = [];

        for ($heure = 0; $heure < 24; $heure++) {
            $sessionsHeure = $parHeure->get($heure) ?? collect();

            $histogramme[] = [
                'heure' => $heure,
                'sessions' => $sessionsHeure->count(),
                'duree_min' => (int) round($sessionsHeure->sum(fn (SessionCuisson $s) => $this->dureeMinutes($s))),
            ];
        }

        return $histogramme;
    }

    /**
     * Heure (0..23) avec le plus de sessions dans `cuissonParHeure()` —
     * « heure de pointe » de la cuisson. En cas d'égalité, la première heure
     * (la plus tôt dans la journée) l'emporte — choix arbitraire mais stable
     * (parcours croissant des heures). `null` si aucune cuisson sur la
     * période (fallback documenté : pas d'heure de pointe sans cuisson).
     *
     * @param  array<int, array{heure: int, sessions: int, duree_min: int}>  $cuissonParHeure
     */
    private function heurePointeCuisson(array $cuissonParHeure): ?int
    {
        $meilleure = null;

        foreach ($cuissonParHeure as $bucket) {
            if ($bucket['sessions'] > 0 && ($meilleure === null || $bucket['sessions'] > $meilleure['sessions'])) {
                $meilleure = $bucket;
            }
        }

        return $meilleure['heure'] ?? null;
    }

    /**
     * Libellé de la période de la journée dominante, déduit de
     * `heure_pointe_cuisson` — découpage usuel d'une journée (aucune
     * référence normative, choix produit documenté) : matin [5h-11h[,
     * midi [11h-14h[, après-midi [14h-18h[, soir [18h-5h[ (le soir couvre
     * aussi la nuit, plage résiduelle).
     */
    private function periodeDominante(int $heure): string
    {
        return match (true) {
            $heure >= 5 && $heure < 11 => 'matin',
            $heure >= 11 && $heure < 14 => 'midi',
            $heure >= 14 && $heure < 18 => 'apres_midi',
            default => 'soir',
        };
    }

    /**
     * Fréquence de cuisson sur la période : nombre moyen de sessions par
     * jour (rapporté à tous les jours de la période, pas seulement ceux avec
     * cuisson — mesure de densité globale), nombre de jours distincts avec
     * au moins une cuisson, durée moyenne d'une session (minutes).
     *
     * @param  Collection<int, SessionCuisson>  $sessions
     * @return array{sessions_par_jour: float, jours_cuisine: int, duree_moyenne_min: float}
     */
    private function frequence(Collection $sessions, CarbonImmutable $debut, CarbonImmutable $fin): array
    {
        $joursPeriode = max(1, $debut->startOfDay()->diffInDays($fin->startOfDay()) + 1);

        $joursCuisine = $sessions->map(fn (SessionCuisson $s) => $s->debut_at->toDateString())->unique()->count();

        $dureesMinutes = $sessions->map(fn (SessionCuisson $s) => $this->dureeMinutes($s));

        return [
            'sessions_par_jour' => round($sessions->count() / $joursPeriode, 2),
            'jours_cuisine' => $joursCuisine,
            'duree_moyenne_min' => $dureesMinutes->isNotEmpty() ? round($dureesMinutes->avg(), 1) : 0.0,
        ];
    }

    /**
     * Série journalière (mini-calendrier/heatmap) : pour chaque jour ayant au
     * moins un relevé de température ou une cuisson sur la période, la
     * température maximale observée ce jour-là et le nombre/la durée cumulée
     * (minutes) des sessions démarrées ce jour. Jours sans aucune donnée
     * omis (pas d'entrée à zéro pour chaque jour de la période) — cohérent
     * avec `AgregationAnalyse::cuisson()`.
     *
     * @param  Collection<int, Temperature>  $temperatures
     * @param  Collection<int, SessionCuisson>  $sessions
     * @return array<int, array{date: string, temp_max_c: ?float, sessions: int, duree_min: int}>
     */
    private function serieJournaliere(Collection $temperatures, Collection $sessions): array
    {
        $parJour = [];

        foreach ($temperatures->groupBy(fn (Temperature $t) => $t->mesure_at->toDateString()) as $date => $relevesJour) {
            $parJour[$date] ??= ['date' => $date, 'temp_max_c' => null, 'sessions' => 0, 'duree_min' => 0];
            $parJour[$date]['temp_max_c'] = round($relevesJour->max('temp_c'), 2);
        }

        foreach ($sessions->groupBy(fn (SessionCuisson $s) => $s->debut_at->toDateString()) as $date => $sessionsJour) {
            $parJour[$date] ??= ['date' => $date, 'temp_max_c' => null, 'sessions' => 0, 'duree_min' => 0];
            $parJour[$date]['sessions'] = $sessionsJour->count();
            $parJour[$date]['duree_min'] = (int) round($sessionsJour->sum(fn (SessionCuisson $s) => $this->dureeMinutes($s)));
        }

        ksort($parJour);

        return array_values($parJour);
    }

    /**
     * Durée d'une session (minutes) : session encore ouverte (`fin_at`
     * `null`) comptée jusqu'à `now()`, même convention que
     * `AgregationAnalyse::cuisson()`.
     */
    private function dureeMinutes(SessionCuisson $session): float
    {
        $fin = $session->fin_at ?? CarbonImmutable::now();

        return max(0, $session->debut_at->diffInSeconds($fin) / 60);
    }
}

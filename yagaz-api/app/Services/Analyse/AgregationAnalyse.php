<?php

namespace App\Services\Analyse;

use App\Enums\ModePaiement;
use App\Enums\RoleBouteille;
use App\Enums\StatutCommande;
use App\Enums\StatutPaiement;
use App\Models\Bouteille;
use App\Models\Commande;
use App\Models\Mesure;
use App\Models\Paiement;
use App\Models\SessionCuisson;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Tableau d'analyses du foyer (doc 13, §2) : agrégats de consommation,
 * dépense, recharges, répartition, jours de cuisine et projection, calculés
 * à partir de l'existant (`mesures`, `commandes`, `paiements`,
 * `sessions_cuisson`, `niveaux_courants`).
 *
 * SQL portable SQLite/PostgreSQL, même approche que `AgregationRegionale`
 * (doc 11 §2) : les regroupements par période sont faits en PHP, jamais par
 * une expression de date propre à un moteur (`date_trunc`/`strftime`).
 * Périmètre déjà cloisonné par l'appelant (`ResoutPerimetreFoyer`).
 */
final class AgregationAnalyse
{
    /**
     * Même hypothèse de tarif que `PaiementMobileMoney::PRIX_XOF_PAR_BOUTEILLE`
     * (ADR 0010) : aucune grille tarifaire fournie par le PRD à ce jour. Sert
     * à estimer la dépense/le coût d'une recharge réglée à la livraison, donc
     * sans ligne `Paiement` (mode `a_la_livraison`).
     */
    private const int PRIX_XOF_PAR_BOUTEILLE = 6_500;

    private const int LIMITE_LIGNES = 10_000;

    /**
     * @param  Collection<int, int>  $siteIds
     * @return array<string, mixed>
     */
    public function analyser(Collection $siteIds, string $periode): array
    {
        [$debut, $fin] = $this->bornesPeriode($periode);
        [$debutPrecedent, $finPrecedent] = $this->bornesPeriodePrecedente($periode, $debut);

        $bouteilleIds = Bouteille::whereIn('site_id', $siteIds)->pluck('id');

        $consommationActuelle = $this->consommationKg($bouteilleIds, $debut, $fin);
        $consommationPrecedente = $this->consommationKg($bouteilleIds, $debutPrecedent, $finPrecedent);

        $depenseActuelle = $this->depenseFcfa($siteIds, $debut, $fin);
        $depensePrecedente = $this->depenseFcfa($siteIds, $debutPrecedent, $finPrecedent);

        return [
            'periode' => $periode,
            'debut' => $debut->toIso8601String(),
            'fin' => $fin->toIso8601String(),
            'consommation_kg' => round($consommationActuelle, 2),
            'consommation_tendance_pct' => $this->tendancePct($consommationActuelle, $consommationPrecedente),
            'depense_fcfa' => $depenseActuelle,
            'depense_tendance_pct' => $this->tendancePct((float) $depenseActuelle, (float) $depensePrecedente),
            'recharges' => $this->recharges($siteIds, $debut, $fin),
            'repartition' => $this->repartition($bouteilleIds, $debut, $fin),
            'jours_cuisine' => $this->cuisson($siteIds, $debut, $fin),
            'autonomie_moyenne_h' => $this->autonomieMoyenneH($bouteilleIds),
            'projection_prochaine_recharge_jours' => $this->projectionProchaineRechargeJours($bouteilleIds),
            'serie_consommation' => $this->serieConsommation($bouteilleIds, $debut, $fin, $periode),
        ];
    }

    /**
     * Bornes [début, fin] de la période courante (semaine ISO, mois civil,
     * année civile).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function bornesPeriode(string $periode): array
    {
        $maintenant = CarbonImmutable::now();

        return match ($periode) {
            'semaine' => [$maintenant->startOfWeek(), $maintenant->endOfWeek()],
            'annee' => [$maintenant->startOfYear(), $maintenant->endOfYear()],
            default => [$maintenant->startOfMonth(), $maintenant->endOfMonth()],
        };
    }

    /**
     * Bornes de la période immédiatement précédente, de même durée — base de
     * comparaison de `tendance_pct`.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function bornesPeriodePrecedente(string $periode, CarbonImmutable $debut): array
    {
        $finPrecedente = $debut->subSecond();

        $debutPrecedent = match ($periode) {
            'semaine' => $debut->subWeek(),
            'annee' => $debut->subYear(),
            default => $debut->subMonthNoOverflow(),
        };

        return [$debutPrecedent, $finPrecedente];
    }

    /**
     * Consommation (kg) estimée sur `[debut, fin]` à partir de la baisse de
     * `mesures.gaz_g` des bouteilles du périmètre (doc 13 §2) : somme des
     * deltas négatifs entre mesures successives d'une même bouteille — une
     * remontée (delta positif ou nul) est une recharge/un plateau, exclue de
     * la consommation, même principe que `Autonomie::estimerDebit()`.
     *
     * Fallback documenté : si aucune mesure n'est disponible sur la période
     * (bouteille sans plateau, ou plateau installé depuis peu), la
     * consommation renvoyée est `0.0` — aucune estimation de repli n'est
     * tentée, faute de source fiable alternative.
     *
     * @param  Collection<int, int>  $bouteilleIds
     */
    private function consommationKg(Collection $bouteilleIds, CarbonImmutable $debut, CarbonImmutable $fin): float
    {
        if ($bouteilleIds->isEmpty()) {
            return 0.0;
        }

        $mesures = Mesure::whereIn('bouteille_id', $bouteilleIds)
            ->whereBetween('mesure_at', [$debut, $fin])
            ->whereNotNull('gaz_g')
            ->orderBy('bouteille_id')
            ->orderBy('mesure_at')
            ->limit(self::LIMITE_LIGNES)
            ->get(['bouteille_id', 'mesure_at', 'gaz_g']);

        $totalG = 0;

        foreach ($mesures->groupBy('bouteille_id') as $mesuresBouteille) {
            foreach ($mesuresBouteille->sliding(2) as $paire) {
                $delta = $paire->last()->gaz_g - $paire->first()->gaz_g;

                if ($delta < 0) {
                    $totalG += -$delta;
                }
            }
        }

        return $totalG / 1000;
    }

    /**
     * Dépense (FCFA/XOF) sur `[debut, fin]` (doc 13 §2) : somme des
     * `paiements` réglés (résolution = `updated_at`, seule sauvegarde faite
     * par `PaiementMobileMoney::appliquerStatut()`), complétée en fallback
     * par une estimation des commandes livrées payées à la livraison (aucune
     * ligne `Paiement` associée dans ce mode), au tarif unitaire supposé.
     *
     * @param  Collection<int, int>  $siteIds
     */
    private function depenseFcfa(Collection $siteIds, CarbonImmutable $debut, CarbonImmutable $fin): int
    {
        $paiementsRegles = (int) Paiement::whereHas('commande', fn ($requete) => $requete->whereIn('site_id', $siteIds))
            ->where('statut', StatutPaiement::Regle)
            ->whereBetween('updated_at', [$debut, $fin])
            ->sum('montant');

        $commandesALaLivraison = Commande::whereIn('site_id', $siteIds)
            ->where('mode_paiement', ModePaiement::ALaLivraison)
            ->where('statut', StatutCommande::Livree)
            ->whereBetween('updated_at', [$debut, $fin])
            ->limit(self::LIMITE_LIGNES)
            ->get(['quantite']);

        $estimationALaLivraison = (int) $commandesALaLivraison->sum(
            fn (Commande $commande) => $commande->quantite * self::PRIX_XOF_PAR_BOUTEILLE
        );

        return $paiementsRegles + $estimationALaLivraison;
    }

    /**
     * Recharges livrées sur `[debut, fin]` (doc 13 §2) : nombre, coût moyen
     * estimé (même tarif unitaire supposé), fréquence moyenne en jours entre
     * livraisons successives (diffs entre dates de livraison triées).
     *
     * @param  Collection<int, int>  $siteIds
     * @return array{nombre: int, cout_moyen_fcfa: int, frequence_jours: ?float}
     */
    private function recharges(Collection $siteIds, CarbonImmutable $debut, CarbonImmutable $fin): array
    {
        $commandesLivrees = Commande::whereIn('site_id', $siteIds)
            ->where('statut', StatutCommande::Livree)
            ->with('livraison')
            ->whereBetween('updated_at', [$debut, $fin])
            ->limit(self::LIMITE_LIGNES)
            ->get();

        $nombre = $commandesLivrees->count();

        $coutMoyen = $nombre > 0
            ? (int) round($commandesLivrees->avg(fn (Commande $c) => $c->quantite * self::PRIX_XOF_PAR_BOUTEILLE))
            : 0;

        $datesLivraison = $commandesLivrees
            ->map(fn (Commande $c) => $c->livraison?->livree_at ?? $c->updated_at)
            ->filter()
            ->map(fn ($date) => CarbonImmutable::parse($date))
            ->sort()
            ->values();

        $frequenceJours = null;

        if ($datesLivraison->count() >= 2) {
            $intervalles = $datesLivraison->sliding(2)
                ->map(fn ($paire) => $paire->first()->diffInDays($paire->last()));

            $frequenceJours = round($intervalles->avg(), 1);
        }

        return [
            'nombre' => $nombre,
            'cout_moyen_fcfa' => $coutMoyen,
            'frequence_jours' => $frequenceJours,
        ];
    }

    /**
     * Répartition de la consommation (kg) par bouteille et par site, pour un
     * donut (doc 13 §2) : la consommation — plutôt que la dépense — est
     * retenue comme métrique commune aux deux vues, une dépense n'étant
     * rattachée qu'à une commande/un site, jamais à une bouteille précise.
     *
     * @param  Collection<int, int>  $bouteilleIds
     * @return array{par_bouteille: array<int, array{libelle: string, valeur: float, couleur: string}>, par_site: array<int, array{libelle: string, valeur: float, couleur: string}>}
     */
    private function repartition(Collection $bouteilleIds, CarbonImmutable $debut, CarbonImmutable $fin): array
    {
        $bouteilles = Bouteille::whereIn('id', $bouteilleIds)
            ->with(['format.marqueRef', 'site'])
            ->get();

        $parBouteille = [];
        $parSite = [];

        foreach ($bouteilles as $bouteille) {
            $consommationKg = round($this->consommationKg(collect([$bouteille->id]), $debut, $fin), 2);
            $couleur = $bouteille->format?->marqueRef?->couleur ?? '#94a3b8';

            $parBouteille[] = [
                'libelle' => $bouteille->format?->code ?? ('Bouteille #'.$bouteille->id),
                'valeur' => $consommationKg,
                'couleur' => $couleur,
            ];

            $nomSite = $bouteille->site?->nom ?? 'Site';
            $parSite[$nomSite] ??= ['libelle' => $nomSite, 'valeur' => 0.0, 'couleur' => $couleur];
            $parSite[$nomSite]['valeur'] = round($parSite[$nomSite]['valeur'] + $consommationKg, 2);
        }

        return [
            'par_bouteille' => $parBouteille,
            'par_site' => array_values($parSite),
        ];
    }

    /**
     * Jours de cuisine (doc 13 §2, ADR 0011) : nombre de jours distincts avec
     * au moins une `SessionCuisson` sur `[debut, fin]`, et série journalière
     * (sessions, durée cumulée en minutes) pour un calendrier heatmap.
     *
     * Une session à cheval sur deux jours (ou débordant de la période) est
     * comptée sur son jour de début, borné à la période — simplification
     * documentée, pas de répartition proportionnelle entre jours.
     *
     * @param  Collection<int, int>  $siteIds
     * @return array{nombre: int, serie_journaliere: array<int, array{date: string, sessions: int, duree_min: int}>}
     */
    private function cuisson(Collection $siteIds, CarbonImmutable $debut, CarbonImmutable $fin): array
    {
        $sessions = SessionCuisson::whereIn('site_id', $siteIds)
            ->where('debut_at', '<=', $fin)
            ->where(function ($requete) use ($debut): void {
                $requete->whereNull('fin_at')->orWhere('fin_at', '>=', $debut);
            })
            ->limit(self::LIMITE_LIGNES)
            ->get();

        $parJour = [];

        foreach ($sessions as $session) {
            $debutSession = CarbonImmutable::parse($session->debut_at);
            $finSession = $session->fin_at !== null ? CarbonImmutable::parse($session->fin_at) : CarbonImmutable::now();

            $jourAffichage = $debutSession->lt($debut) ? $debut : $debutSession;
            $borneFin = $finSession->gt($fin) ? $fin : $finSession;

            $cle = $jourAffichage->toDateString();
            $parJour[$cle] ??= ['date' => $cle, 'sessions' => 0, 'duree_min' => 0];
            $parJour[$cle]['sessions']++;
            $parJour[$cle]['duree_min'] += max(0, (int) round($debutSession->diffInSeconds($borneFin) / 60));
        }

        ksort($parJour);

        return [
            'nombre' => count($parJour),
            'serie_journaliere' => array_values($parJour),
        ];
    }

    /**
     * Autonomie moyenne (heures) des bouteilles actives du périmètre (doc 13
     * §2) : réutilise directement `niveaux_courants.autonomie_min`, déjà
     * calculé par `Autonomie::autonomieMinutes()` à chaque ingestion — pas de
     * second calcul dupliqué ici.
     *
     * @param  Collection<int, int>  $bouteilleIds
     */
    private function autonomieMoyenneH(Collection $bouteilleIds): ?float
    {
        $autonomiesMin = Bouteille::whereIn('id', $bouteilleIds)
            ->where('role_bouteille', RoleBouteille::Active)
            ->with('niveauCourant')
            ->get()
            ->map(fn (Bouteille $b) => $b->niveauCourant?->autonomie_min)
            ->filter(fn ($minutes) => $minutes !== null);

        if ($autonomiesMin->isEmpty()) {
            return null;
        }

        return round($autonomiesMin->avg() / 60, 1);
    }

    /**
     * Projection de la prochaine recharge (jours), au rythme de consommation
     * observé (doc 13 §2) : pour chaque bouteille active du périmètre, temps
     * estimé pour atteindre son seuil bas (`seuil_bas_pct` de la contenance
     * du format), au débit courant `niveaux_courants.debit_g_par_h` (déjà
     * estimé par `Autonomie::estimerDebit()`). Moyenne entre bouteilles
     * quand le périmètre en couvre plusieurs (plusieurs sites).
     *
     * `null` si aucune bouteille active du périmètre n'a de débit observé
     * positif (pas encore de mesure exploitable) — fallback documenté.
     *
     * @param  Collection<int, int>  $bouteilleIds
     */
    private function projectionProchaineRechargeJours(Collection $bouteilleIds): ?float
    {
        $bouteilles = Bouteille::whereIn('id', $bouteilleIds)
            ->where('role_bouteille', RoleBouteille::Active)
            ->with(['niveauCourant', 'format'])
            ->get();

        $projectionsJours = collect();

        foreach ($bouteilles as $bouteille) {
            $niveau = $bouteille->niveauCourant;
            $debit = $niveau?->debit_g_par_h;

            if ($niveau === null || $debit === null || $debit <= 0) {
                continue;
            }

            $contenanceGazG = $bouteille->format?->contenance_gaz_g ?? 0;
            $seuilGazG = $contenanceGazG * $bouteille->seuil_bas_pct / 100;
            $deltaG = max(0, $niveau->gaz_g - $seuilGazG);

            $projectionsJours->push($deltaG / $debit / 24);
        }

        if ($projectionsJours->isEmpty()) {
            return null;
        }

        return round($projectionsJours->avg(), 1);
    }

    /**
     * Série temporelle de consommation (kg), pour une courbe (doc 13 §2) :
     * même méthode de calcul que `consommationKg()` (deltas négatifs de
     * `gaz_g`), regroupée en PHP par jour (périodes « semaine »/« mois ») ou
     * par mois (période « année ») — portabilité SQLite/PostgreSQL, comme
     * `AgregationRegionale::periode()`.
     *
     * @param  Collection<int, int>  $bouteilleIds
     * @return array<int, array{periode: string, consommation_kg: float}>
     */
    private function serieConsommation(Collection $bouteilleIds, CarbonImmutable $debut, CarbonImmutable $fin, string $periode): array
    {
        if ($bouteilleIds->isEmpty()) {
            return [];
        }

        $mesures = Mesure::whereIn('bouteille_id', $bouteilleIds)
            ->whereBetween('mesure_at', [$debut, $fin])
            ->whereNotNull('gaz_g')
            ->orderBy('bouteille_id')
            ->orderBy('mesure_at')
            ->limit(self::LIMITE_LIGNES)
            ->get(['bouteille_id', 'mesure_at', 'gaz_g']);

        $parBucket = [];

        foreach ($mesures->groupBy('bouteille_id') as $mesuresBouteille) {
            foreach ($mesuresBouteille->sliding(2) as $paire) {
                $delta = $paire->last()->gaz_g - $paire->first()->gaz_g;

                if ($delta >= 0) {
                    continue;
                }

                $bucket = $this->bucket(CarbonImmutable::parse($paire->last()->mesure_at), $periode);
                $parBucket[$bucket] = ($parBucket[$bucket] ?? 0) + (-$delta);
            }
        }

        ksort($parBucket);

        return collect($parBucket)
            ->map(fn ($grammes, $bucket) => ['periode' => $bucket, 'consommation_kg' => round($grammes / 1000, 2)])
            ->values()
            ->all();
    }

    private function bucket(CarbonImmutable $date, string $periode): string
    {
        return match ($periode) {
            'annee' => $date->format('Y-m'),
            default => $date->toDateString(),
        };
    }

    /**
     * Variation en % entre la période courante et la précédente. `null` si
     * la précédente est nulle/négative (pas de base de comparaison valable —
     * fallback documenté, doc 13 §2).
     */
    private function tendancePct(float $actuelle, float $precedente): ?float
    {
        if ($precedente <= 0.0) {
            return null;
        }

        return round(($actuelle - $precedente) / $precedente * 100, 1);
    }
}

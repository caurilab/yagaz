<?php

namespace App\Services\Distributeur;

use App\Enums\OrigineCommande;
use App\Enums\StatutCommande;
use App\Enums\TypeOrganisation;
use App\Models\Commande;
use App\Models\Organisation;
use App\Models\Stock;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Agrégats régionaux d'un distributeur (contrat API doc 11, §2) : demande par
 * zone/format/période, tensions par zone, évolution des volumes. **Jamais**
 * de donnée individuelle de foyer (doc 04 §8, doc 07 §9) — seules des sommes
 * par zone (`organisations.zone` du dépôt) sont manipulées ici, aucune
 * requête ne touche `sites`/`bouteilles`/`mesures`.
 *
 * Portabilité SQLite/PostgreSQL (doc 11, §2) : plutôt qu'un `GROUP BY` sur
 * une expression de date propre à chaque moteur (`date_trunc` Postgres-only,
 * `strftime` SQLite-only), les commandes de la période sont chargées une
 * fois (fetch borné par `depuis`/`jusqua`) puis regroupées en PHP — portable
 * par construction. Les agrégations continues (Timescale) pourront
 * remplacer ce calcul à la volée sans changer le contrat (doc 11, §2).
 */
final class AgregationRegionale
{
    /**
     * Demande agrégée par zone et par format, dans le temps (contrat API,
     * `GET /api/distributeurs/{orgUuid}/demande`).
     *
     * @return array<int, array{zone: ?string, format: array{id: int, code: ?string}, periode: string, quantite: int, commandes: int}>
     */
    public function demande(Organisation $distributeur, CarbonImmutable $depuis, CarbonImmutable $jusqua, string $pas): array
    {
        $groupes = [];

        foreach ($this->commandesDeLaBranche($distributeur, $depuis, $jusqua) as $commande) {
            $zone = $this->zoneDeLaCommande($commande);
            $periode = $this->periode($commande->created_at, $pas);
            $cle = $zone.'|'.$commande->format_id.'|'.$periode;

            $groupes[$cle] ??= [
                'zone' => $zone,
                'format' => ['id' => $commande->format_id, 'code' => $commande->format?->code],
                'periode' => $periode,
                'quantite' => 0,
                'commandes' => 0,
            ];

            $groupes[$cle]['quantite'] += $commande->quantite;
            $groupes[$cle]['commandes']++;
        }

        return array_values($groupes);
    }

    /**
     * Évolution des volumes distribués dans le temps, par zone (contrat API,
     * `GET /api/distributeurs/{orgUuid}/volumes`) — comparaison entre zones,
     * saisonnalité (doc 11, §2). Même agrégat que `demande()`, sans le
     * détail par format.
     *
     * @return array<int, array{zone: ?string, periode: string, quantite: int, commandes: int}>
     */
    public function volumes(Organisation $distributeur, CarbonImmutable $depuis, CarbonImmutable $jusqua, string $pas): array
    {
        $groupes = [];

        foreach ($this->commandesDeLaBranche($distributeur, $depuis, $jusqua) as $commande) {
            $zone = $this->zoneDeLaCommande($commande);
            $periode = $this->periode($commande->created_at, $pas);
            $cle = $zone.'|'.$periode;

            $groupes[$cle] ??= [
                'zone' => $zone,
                'periode' => $periode,
                'quantite' => 0,
                'commandes' => 0,
            ];

            $groupes[$cle]['quantite'] += $commande->quantite;
            $groupes[$cle]['commandes']++;
        }

        return array_values($groupes);
    }

    /**
     * Tensions par zone — carte de chaleur (contrat API,
     * `GET /api/distributeurs/{orgUuid}/zones`) : nombre de dépôts en
     * rupture (stock plein au ou sous son seuil bas) et vides accumulés.
     *
     * @return array<int, array{zone: ?string, depots_en_tension: int, vides_accumules: int}>
     */
    public function zones(Organisation $distributeur): array
    {
        $groupes = [];

        foreach ($this->depotsDeLaBranche($distributeur)->load('stocks') as $depot) {
            $zone = $depot->zone;

            $groupes[$zone] ??= [
                'zone' => $zone,
                'depots_en_tension' => 0,
                'vides_accumules' => 0,
            ];

            $enTension = $depot->stocks->contains(fn (Stock $stock) => $stock->pleines <= $stock->seuil_plein_bas);

            if ($enTension) {
                $groupes[$zone]['depots_en_tension']++;
            }

            $groupes[$zone]['vides_accumules'] += (int) $depot->stocks->sum('vides');
        }

        return array_values($groupes);
    }

    /**
     * Dépôts de la branche du distributeur : ses mandataires enfants, et les
     * dépôts enfants de ceux-ci (doc 07, §10 — hiérarchie descendante).
     *
     * @return Collection<int, Organisation>
     */
    private function depotsDeLaBranche(Organisation $distributeur): Collection
    {
        $mandataireIds = Organisation::where('parent_id', $distributeur->id)
            ->where('type', TypeOrganisation::Mandataire->value)
            ->pluck('id');

        return Organisation::where('type', TypeOrganisation::Depot->value)
            ->whereIn('parent_id', $mandataireIds)
            ->get();
    }

    /**
     * Commandes de la branche du distributeur, dans la période : commandes
     * foyer livrées à un de ses dépôts, et réappros émis par un de ses
     * dépôts vers son mandataire (doc 11, §1 et §2).
     *
     * @return Collection<int, Commande>
     */
    private function commandesDeLaBranche(Organisation $distributeur, CarbonImmutable $depuis, CarbonImmutable $jusqua): Collection
    {
        $depotIds = $this->depotsDeLaBranche($distributeur)->pluck('id');

        if ($depotIds->isEmpty()) {
            return collect();
        }

        return Commande::query()
            ->where(function ($query) use ($depotIds) {
                // Commandes foyer : seules celles effectivement `livree`
                // comptent comme demande satisfaite (doc 11 §2, « commandes
                // livrées »). Réappros : tout statut compte comme signal de
                // demande, dès l'émission (doc 11 §1 : la tension déclenche
                // déjà la proposition, sans attendre sa livraison).
                $query->where(function ($sousRequete) use ($depotIds) {
                    $sousRequete->where('origine', OrigineCommande::Foyer->value)
                        ->where('statut', StatutCommande::Livree->value)
                        ->whereIn('cible_org_id', $depotIds);
                })->orWhere(function ($sousRequete) use ($depotIds) {
                    $sousRequete->where('origine', OrigineCommande::Depot->value)
                        ->whereIn('demandeur_org_id', $depotIds);
                });
            })
            ->whereBetween('created_at', [$depuis, $jusqua])
            ->with(['format', 'cibleOrg', 'demandeurOrg'])
            // Filet de sécurité (audit sécurité, [MOYEN] anti-DoS agrégats
            // distributeur) : la fenêtre `depuis`/`jusqua` est déjà bornée à
            // 24 mois par `DistributeurPeriodeRequest`, mais une borne dure
            // sur le nombre de lignes chargées protège aussi contre une
            // branche anormalement volumineuse (beaucoup de dépôts/commandes
            // sur la période) sans dépendre uniquement de la validation amont.
            ->limit(100000)
            ->get();
    }

    /**
     * Zone d'une commande : celle du dépôt cible (commande foyer) ou du
     * dépôt demandeur (réappro dépôt→mandataire) — jamais celle du foyer lui
     * même (aucune colonne de ce type n'existe sur `commandes`/`sites`).
     */
    private function zoneDeLaCommande(Commande $commande): ?string
    {
        return $commande->origine === OrigineCommande::Foyer
            ? $commande->cibleOrg?->zone
            : $commande->demandeurOrg?->zone;
    }

    /**
     * Période (jour/semaine/mois) portant `$date`, en clé de tri stable
     * (ISO, croissante) — calcul Carbon pur, portable par construction.
     */
    private function periode(?CarbonInterface $date, string $pas): string
    {
        $date ??= CarbonImmutable::now();

        return match ($pas) {
            'semaine' => $date->copy()->startOfWeek()->toDateString(),
            'mois' => $date->format('Y-m'),
            default => $date->toDateString(),
        };
    }
}

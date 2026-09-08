<?php

namespace App\Services\Historique;

use App\Enums\OrigineCommande;
use App\Enums\StatutCommande;
use App\Enums\StatutPaiement;
use App\Enums\TypeAlerte;
use App\Models\Alerte;
use App\Models\Bouteille;
use App\Models\Commande;
use App\Models\Paiement;
use App\Models\SessionCuisson;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Historique unifié du foyer (doc 13, §1) : fusionne en une timeline triée
 * récent→ancien les commandes, paiements, alertes et sessions de cuisson du
 * périmètre foyer (identifiants de site déjà cloisonnés par l'appelant —
 * `ResoutPerimetreFoyer`).
 *
 * Portabilité SQLite/PostgreSQL, même approche que `AgregationRegionale`
 * (doc 11 §2) : chaque source est chargée par une requête simple (pas
 * d'UNION SQL inter-tables hétérogènes), puis fusionnée/triée/filtrée en PHP.
 *
 * Bornage défensif (anti-DoS, même esprit que `AgregationRegionale::
 * commandesDeLaBranche()`) : chaque source est plafonnée à 10 000 lignes —
 * un foyer ne devrait jamais en approcher le volume, mais protège contre un
 * périmètre anormalement volumineux.
 *
 * Jalons bouteille (tare calibrée fiable, changement actif/secours) omis
 * volontairement (doc 13 §1, écart assumé) : aucune colonne d'horodatage ne
 * porte le moment de ces transitions (`bouteilles.tare_fiable`/
 * `role_bouteille` sont des états courants sans historique dédié). Les
 * dériver exigerait une table d'audit dédiée (hors périmètre de cette
 * itération) — une approximation par `bouteilles.updated_at` serait fausse
 * dès qu'un autre champ de la bouteille change après coup (ex. `plateau_id`).
 */
final class AgregationHistorique
{
    /**
     * Même hypothèse de tarif que `PaiementMobileMoney::PRIX_XOF_PAR_BOUTEILLE`
     * (ADR 0010) : aucune grille tarifaire fournie par le PRD à ce jour. Sert
     * uniquement à afficher un montant estimé sur les événements de commande
     * (le montant réel d'un paiement effectif reste celui de `Paiement::montant`).
     */
    private const int PRIX_XOF_PAR_BOUTEILLE = 6_500;

    private const int LIMITE_LIGNES = 10_000;

    /**
     * @param  Collection<int, int>  $siteIds
     * @return Collection<int, array{type: string, date: CarbonImmutable, titre: string, detail: ?string, montant: ?int, statut: ?string, icone: string}>
     */
    public function evenements(Collection $siteIds, ?CarbonImmutable $depuis, ?string $type): Collection
    {
        $evenements = collect()
            ->concat($this->evenementsCommande($siteIds))
            ->concat($this->evenementsPaiement($siteIds))
            ->concat($this->evenementsAlerte($siteIds))
            ->concat($this->evenementsCuisson($siteIds));

        if ($depuis !== null) {
            $evenements = $evenements->filter(fn (array $evenement): bool => $evenement['date']->gte($depuis));
        }

        if ($type !== null) {
            $typeRecherche = mb_strtolower($type);
            $evenements = $evenements->filter(
                fn (array $evenement): bool => mb_strtolower($evenement['type']) === $typeRecherche
                    || mb_strtolower($evenement['icone']) === $typeRecherche
            );
        }

        return $evenements->sortByDesc(fn (array $evenement) => $evenement['date']->timestamp)->values();
    }

    /**
     * Jalons d'une commande : création, confirmation, livraison (doc 13 §1).
     *
     * Horodatages disponibles (doc 07 §6) : `commandes` ne porte que
     * `created_at`/`updated_at`, aucune colonne par transition. On dérive
     * donc :
     * - « créée » : `created_at`, toujours ;
     * - « confirmée » : émise seulement si la commande est (ou a été)
     *   `confirmee` ou au-delà. Une commande foyer part directement
     *   confirmée à la création (`CycleCommande::creerDepuisFoyer`) — même
     *   instant que « créée ». Une proposition dépôt confirmée par le foyer
     *   utilise `updated_at` comme proxy (seule donnée disponible ;
     *   approximation documentée qui devient inexacte si la commande a
     *   progressé depuis — acceptable pour une timeline informative) ;
     * - « livrée » : `livraisons.livree_at` (horodatage exact de la
     *   transition, contrairement à `commandes.updated_at`).
     *
     * @param  Collection<int, int>  $siteIds
     * @return Collection<int, array<string, mixed>>
     */
    private function evenementsCommande(Collection $siteIds): Collection
    {
        $commandes = Commande::whereIn('site_id', $siteIds)
            ->with(['format', 'livraison'])
            ->limit(self::LIMITE_LIGNES)
            ->get();

        $evenements = collect();

        foreach ($commandes as $commande) {
            $montant = $commande->quantite * self::PRIX_XOF_PAR_BOUTEILLE;
            $detail = sprintf(
                'Format %s, quantité %d, commission %s g',
                $commande->format?->code ?? '?',
                $commande->quantite,
                $commande->commission_g ?? '0'
            );

            $evenements->push([
                'type' => 'commande_creee',
                'date' => CarbonImmutable::parse($commande->created_at),
                'titre' => 'Commande créée',
                'detail' => $detail,
                'montant' => $montant,
                'statut' => $commande->statut->value,
                'icone' => 'commande',
            ]);

            $confirmeeOuApres = in_array($commande->statut, [
                StatutCommande::Confirmee,
                StatutCommande::Preparee,
                StatutCommande::EnLivraison,
                StatutCommande::Livree,
            ], true);

            if ($confirmeeOuApres) {
                $dateConfirmation = $commande->origine === OrigineCommande::Foyer
                    ? CarbonImmutable::parse($commande->created_at)
                    : CarbonImmutable::parse($commande->updated_at);

                $evenements->push([
                    'type' => 'commande_confirmee',
                    'date' => $dateConfirmation,
                    'titre' => 'Commande confirmée',
                    'detail' => $detail,
                    'montant' => $montant,
                    'statut' => $commande->statut->value,
                    'icone' => 'commande',
                ]);
            }

            $livraison = $commande->livraison;

            if ($livraison !== null && $livraison->livree_at !== null) {
                $evenements->push([
                    'type' => 'commande_livree',
                    'date' => CarbonImmutable::parse($livraison->livree_at),
                    'titre' => 'Commande livrée',
                    'detail' => $detail,
                    'montant' => $montant,
                    'statut' => $commande->statut->value,
                    'icone' => 'commande',
                ]);
            }
        }

        return $evenements;
    }

    /**
     * Jalons d'un paiement : initiation, résolution (réglé/échoué — doc 13 §1).
     *
     * `updated_at` fait foi pour la résolution : `PaiementMobileMoney::
     * appliquerStatut()` ne sauvegarde le paiement qu'une seule fois, au
     * moment précis de la résolution finale (webhook vérifié ou
     * réconciliation) — ce timestamp est donc exact ici, contrairement à
     * celui d'une commande.
     *
     * @param  Collection<int, int>  $siteIds
     * @return Collection<int, array<string, mixed>>
     */
    private function evenementsPaiement(Collection $siteIds): Collection
    {
        $paiements = Paiement::whereHas('commande', fn ($requete) => $requete->whereIn('site_id', $siteIds))
            ->limit(self::LIMITE_LIGNES)
            ->get();

        $evenements = collect();

        foreach ($paiements as $paiement) {
            $evenements->push([
                'type' => 'paiement_initie',
                'date' => CarbonImmutable::parse($paiement->created_at),
                'titre' => 'Paiement initié',
                'detail' => 'Provider '.$paiement->provider,
                'montant' => $paiement->montant,
                'statut' => $paiement->statut->value,
                'icone' => 'paiement',
            ]);

            if ($paiement->statut === StatutPaiement::Regle || $paiement->statut === StatutPaiement::Echoue) {
                $evenements->push([
                    'type' => $paiement->statut === StatutPaiement::Regle ? 'paiement_regle' : 'paiement_echoue',
                    'date' => CarbonImmutable::parse($paiement->updated_at),
                    'titre' => $paiement->statut === StatutPaiement::Regle ? 'Paiement réglé' : 'Paiement échoué',
                    'detail' => 'Provider '.$paiement->provider,
                    'montant' => $paiement->montant,
                    'statut' => $paiement->statut->value,
                    'icone' => 'paiement',
                ]);
            }
        }

        return $evenements;
    }

    /**
     * Alertes du foyer (seuil bas, température élevée, proposition de
     * livraison — doc 13 §1) : une alerte cible soit une bouteille (dont le
     * site est dans le périmètre), soit directement un site (Phase 5, doc 11
     * §3 — ex. `temperature_elevee`, `proposition_livraison`).
     *
     * @param  Collection<int, int>  $siteIds
     * @return Collection<int, array<string, mixed>>
     */
    private function evenementsAlerte(Collection $siteIds): Collection
    {
        $bouteilleIds = Bouteille::whereIn('site_id', $siteIds)->pluck('id');

        $typesInclus = [
            TypeAlerte::SeuilBas->value,
            TypeAlerte::TemperatureElevee->value,
            TypeAlerte::PropositionLivraison->value,
        ];

        $titres = [
            TypeAlerte::SeuilBas->value => 'Seuil bas atteint',
            TypeAlerte::TemperatureElevee->value => 'Température élevée',
            TypeAlerte::PropositionLivraison->value => 'Proposition de livraison',
        ];

        $alertes = Alerte::where(function ($requete) use ($bouteilleIds, $siteIds): void {
            $requete->whereIn('bouteille_id', $bouteilleIds)
                ->orWhereIn('site_id', $siteIds);
        })
            ->whereIn('type', $typesInclus)
            ->limit(self::LIMITE_LIGNES)
            ->get();

        return $alertes->map(fn (Alerte $alerte): array => [
            'type' => 'alerte_'.$alerte->type->value,
            'date' => CarbonImmutable::parse($alerte->created_at),
            'titre' => $titres[$alerte->type->value] ?? $alerte->type->value,
            'detail' => null,
            'montant' => null,
            'statut' => $alerte->statut->value,
            'icone' => 'alerte',
        ]);
    }

    /**
     * Sessions de cuisson (début/fin, durée — doc 13 §1, ADR 0011). Une
     * session encore ouverte (`fin_at` null) n'émet que son événement de
     * début.
     *
     * @param  Collection<int, int>  $siteIds
     * @return Collection<int, array<string, mixed>>
     */
    private function evenementsCuisson(Collection $siteIds): Collection
    {
        $sessions = SessionCuisson::whereIn('site_id', $siteIds)
            ->limit(self::LIMITE_LIGNES)
            ->get();

        $evenements = collect();

        foreach ($sessions as $session) {
            $debutAt = CarbonImmutable::parse($session->debut_at);

            $evenements->push([
                'type' => 'cuisson_debut',
                'date' => $debutAt,
                'titre' => 'Cuisson démarrée',
                'detail' => null,
                'montant' => null,
                'statut' => $session->fin_at === null ? 'en_cours' : 'terminee',
                'icone' => 'cuisson',
            ]);

            if ($session->fin_at !== null) {
                $finAt = CarbonImmutable::parse($session->fin_at);
                $dureeMin = (int) round($debutAt->diffInSeconds($finAt) / 60);

                $evenements->push([
                    'type' => 'cuisson_fin',
                    'date' => $finAt,
                    'titre' => 'Cuisson terminée',
                    'detail' => "Durée {$dureeMin} min",
                    'montant' => null,
                    'statut' => 'terminee',
                    'icone' => 'cuisson',
                ]);
            }
        }

        return $evenements;
    }
}

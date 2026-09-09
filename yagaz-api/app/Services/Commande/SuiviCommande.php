<?php

namespace App\Services\Commande;

use App\Enums\OrigineCommande;
use App\Enums\StatutCommande;
use App\Models\Commande;
use App\Models\Livraison;
use App\Services\Geo\Distance;

/**
 * Suivi/ETA d'une commande (contrat API, `GET /api/commandes/{uuid}/suivi`) :
 * timeline des étapes dérivée des horodatages disponibles, et une estimation
 * du temps restant avant livraison.
 *
 * Limitation documentée (même esprit que `AgregationHistorique`) : la table
 * `commandes` ne porte que `created_at`/`updated_at`, aucune colonne par
 * transition — seule `livraisons` (`affectee_at`/`en_route_at`/`livree_at`)
 * horodate ses propres transitions avec précision. Les étapes `confirmee` et
 * `preparee` sont donc approximées par `updated_at`/`livraisons.affectee_at`
 * quand aucune donnée plus précise n'est disponible.
 *
 * `eta_minutes` est une simple ESTIMATION (distance à vol d'oiseau
 * dépôt↔site, `App\Services\Geo\Distance`, à une vitesse urbaine moyenne
 * configurée — `config('livraison.vitesse_urbaine_kmh')`) : il n'y a pas de
 * position GPS live du livreur. Elle n'est calculée que lorsque la commande
 * est `en_livraison` et que les deux coordonnées sont connues ; `null` sinon.
 */
final class SuiviCommande
{
    /**
     * Ordre des étapes de la timeline (contrat API) : l'index de la dernière
     * atteinte détermine combien de ces clés sont `atteinte => true`.
     *
     * @var array<int, string>
     */
    private const array ORDRE_ETAPES = ['passee', 'confirmee', 'preparee', 'en_livraison', 'livree'];

    /**
     * @var array<string, string>
     */
    private const array LIBELLES = [
        'passee' => 'Commande passée',
        'confirmee' => 'Commande confirmée',
        'preparee' => 'Commande préparée',
        'en_livraison' => 'En livraison',
        'livree' => 'Livrée',
    ];

    public function __construct(private readonly Distance $distance) {}

    /**
     * @return array<string, mixed>
     */
    public function suivi(Commande $commande): array
    {
        $commande->loadMissing(['cibleOrg', 'site', 'livraison.livreur']);

        $livraison = $commande->livraison;
        $depot = $commande->cibleOrg;

        [$distanceKm, $etaMinutes] = $this->estimerEta($commande);

        return [
            'etapes' => $this->construireEtapes($commande, $livraison),
            'statut_courant' => $commande->statut->value,
            'livraison' => [
                'statut' => $livraison?->statut->value,
                'livreur' => $livraison?->livreur?->name,
            ],
            // Contact du dépôt (appel/WhatsApp) affiché sur l'écran de suivi
            // en cas de retard - `telephone` est nullable côté `organisations`.
            'depot' => [
                'nom' => $depot?->nom,
                'telephone' => $depot?->telephone,
            ],
            'distance_km' => $distanceKm,
            // Estimation grossière (vitesse urbaine moyenne configurée), pas
            // de position GPS live — documenté ci-dessus et dans le contrat API.
            'eta_minutes' => $etaMinutes,
        ];
    }

    /**
     * @return array<int, array{cle: string, libelle: string, atteinte: bool, date: ?string, courante: bool}>
     */
    private function construireEtapes(Commande $commande, ?Livraison $livraison): array
    {
        $indexAtteint = $this->indexEtapeAtteinte($commande);

        $dates = [
            'passee' => optional($commande->created_at)->toIso8601String(),
            'confirmee' => $this->dateConfirmation($commande, $indexAtteint),
            'preparee' => $this->datePreparation($commande, $livraison, $indexAtteint),
            'en_livraison' => $livraison?->en_route_at?->toIso8601String(),
            'livree' => $livraison?->livree_at?->toIso8601String(),
        ];

        $etapes = [];
        foreach (self::ORDRE_ETAPES as $position => $cle) {
            $atteinte = $position <= $indexAtteint;
            $etapes[] = [
                'cle' => $cle,
                'libelle' => self::LIBELLES[$cle],
                'atteinte' => $atteinte,
                'date' => $atteinte ? $dates[$cle] : null,
                'courante' => $position === $indexAtteint,
            ];
        }

        return $etapes;
    }

    /**
     * Index (dans `ORDRE_ETAPES`) de la dernière étape atteinte, déduit du
     * statut courant. `proposee` et `annulee` n'ont jamais dépassé « passée »
     * — une commande n'est annulée que depuis `proposee`
     * (`CycleCommande::repondre`), jamais après confirmation.
     */
    private function indexEtapeAtteinte(Commande $commande): int
    {
        return match ($commande->statut) {
            StatutCommande::Confirmee => 1,
            StatutCommande::Preparee => 2,
            StatutCommande::EnLivraison => 3,
            StatutCommande::Livree => 4,
            default => 0,
        };
    }

    /**
     * Date de confirmation : exacte (`created_at`) pour une commande foyer,
     * qui part directement `confirmee` (`CycleCommande::creerDepuisFoyer`) ;
     * approximée par `updated_at` pour une proposition dépôt confirmée par le
     * foyer (seule donnée disponible — imprécise si la commande a progressé
     * depuis, même limitation documentée qu'`AgregationHistorique`).
     */
    private function dateConfirmation(Commande $commande, int $indexAtteint): ?string
    {
        if ($indexAtteint < 1) {
            return null;
        }

        $date = $commande->origine === OrigineCommande::Foyer
            ? $commande->created_at
            : $commande->updated_at;

        return optional($date)->toIso8601String();
    }

    /**
     * Date de préparation : `livraisons.affectee_at` si un livreur a déjà été
     * affecté (transition immédiatement postérieure à la préparation, proxy
     * proche) ; sinon `commandes.updated_at`, seulement fiable tant que le
     * statut courant est exactement `preparee` (pas encore ré-écrasé par une
     * transition suivante).
     */
    private function datePreparation(Commande $commande, ?Livraison $livraison, int $indexAtteint): ?string
    {
        if ($indexAtteint < 2) {
            return null;
        }

        if ($livraison?->affectee_at !== null) {
            return $livraison->affectee_at->toIso8601String();
        }

        if ($commande->statut === StatutCommande::Preparee) {
            return optional($commande->updated_at)->toIso8601String();
        }

        return null;
    }

    /**
     * @return array{0: ?float, 1: ?int} [distance_km, eta_minutes]
     */
    private function estimerEta(Commande $commande): array
    {
        $depot = $commande->cibleOrg;
        $site = $commande->site;

        $distanceKm = null;

        if ($depot !== null && $site !== null && $depot->lat !== null && $depot->lng !== null) {
            $distanceKm = $this->distance->kilometres(
                (float) $depot->lat,
                (float) $depot->lng,
                $site->lat !== null ? (float) $site->lat : null,
                $site->lng !== null ? (float) $site->lng : null,
            );
        }

        $etaMinutes = null;

        if ($commande->statut === StatutCommande::EnLivraison && $distanceKm !== null) {
            $vitesseKmh = (float) config('livraison.vitesse_urbaine_kmh');

            if ($vitesseKmh > 0) {
                $etaMinutes = (int) round($distanceKm / $vitesseKmh * 60);
            }
        }

        return [$distanceKm, $etaMinutes];
    }
}

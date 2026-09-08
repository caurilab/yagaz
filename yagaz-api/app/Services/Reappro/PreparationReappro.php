<?php

namespace App\Services\Reappro;

use App\Enums\ModePaiement;
use App\Enums\OrigineCommande;
use App\Enums\RoleMembership;
use App\Enums\StatutCommande;
use App\Enums\StatutPaiement;
use App\Enums\TypeAlerte;
use App\Enums\TypeOrganisation;
use App\Models\Commande;
use App\Models\FormatBouteille;
use App\Models\Organisation;
use App\Models\Stock;
use App\Models\User;
use App\Services\Notification\Notificateur;
use Illuminate\Support\Facades\Log;

/**
 * Production automatique d'une proposition de réappro dépôt → mandataire
 * (ADR 0009, maillon D) : quand le stock `pleines` d'un dépôt, pour un
 * format, passe sous `seuil_plein_bas`, prépare (ou met à jour) une
 * commande `origine=depot`, `statut=proposee`, ciblant le mandataire parent.
 *
 * Appelée après chaque changement pertinent de stock (décrément lors d'une
 * préparation de commande foyer, ajustement manuel côté dépôt), toujours
 * DANS la transaction qui modifie le stock — jamais un job différé, la
 * cohérence stock/réappro doit être immédiate (doc 09 §D).
 */
final class PreparationReappro
{
    /**
     * Quantité proposée : de quoi repasser au-dessus du seuil
     * (`seuil_plein_bas * 2 - pleines`), bornée à [1, 100] (doc 09 §D).
     */
    private const int QUANTITE_MIN = 1;

    private const int QUANTITE_MAX = 100;

    /**
     * Statuts d'un réappro considéré comme « non terminé » (doc 09 §D) :
     * une proposition ou un réappro déjà en cours de traitement bloque la
     * création d'un doublon. `livree`/`annulee` sont terminaux — un nouveau
     * réappro peut être proposé après eux.
     *
     * @var list<string>
     */
    private const array STATUTS_NON_TERMINES = [
        StatutCommande::Proposee->value,
        StatutCommande::Confirmee->value,
        StatutCommande::Preparee->value,
        StatutCommande::EnLivraison->value,
    ];

    public function __construct(private readonly Notificateur $notificateur = new Notificateur) {}

    /**
     * Prépare (ou met à jour) le réappro du dépôt pour ce format, si le
     * stock est en tension. Retourne `null` si rien n'a été produit (stock
     * sain, ou dépôt sans mandataire parent — journalisé, pas d'erreur).
     */
    public function preparer(Organisation $depot, FormatBouteille $format): ?Commande
    {
        $stock = Stock::where('organisation_id', $depot->id)
            ->where('format_id', $format->id)
            ->first();

        if ($stock === null || $stock->pleines >= $stock->seuil_plein_bas) {
            return null;
        }

        $mandataire = $depot->parent;

        if ($mandataire === null || $mandataire->type !== TypeOrganisation::Mandataire) {
            Log::warning('PreparationReappro : dépôt sans mandataire parent, aucun réappro produit.', [
                'organisation_id' => $depot->id,
                'format_id' => $format->id,
            ]);

            return null;
        }

        $quantite = max(self::QUANTITE_MIN, min(self::QUANTITE_MAX, $stock->seuil_plein_bas * 2 - $stock->pleines));

        $existant = Commande::where('origine', OrigineCommande::Depot->value)
            ->where('demandeur_org_id', $depot->id)
            ->where('cible_org_id', $mandataire->id)
            ->where('format_id', $format->id)
            ->whereIn('statut', self::STATUTS_NON_TERMINES)
            ->first();

        if ($existant !== null) {
            // Idempotence (doc 09 §D) : seule une proposition encore
            // `proposee` est ajustable — une fois confirmée par le dépôt, on
            // n'écrase plus silencieusement la quantité qu'il a validée.
            if ($existant->statut === StatutCommande::Proposee && $existant->quantite !== $quantite) {
                $existant->quantite = $quantite;
                $existant->save();
            }

            return $existant;
        }

        $commande = new Commande;
        $commande->forceFill([
            'origine' => OrigineCommande::Depot,
            'demandeur_user_id' => null,
            'demandeur_org_id' => $depot->id,
            'cible_org_id' => $mandataire->id,
            'site_id' => null,
            'format_id' => $format->id,
            'quantite' => $quantite,
            'statut' => StatutCommande::Proposee,
            'mode_paiement' => ModePaiement::ALaLivraison,
            'statut_paiement' => StatutPaiement::EnAttente,
            'commission_g' => null,
        ]);
        $commande->save();

        $this->notifierGerantsDepot($depot, $commande);

        return $commande;
    }

    /**
     * Notifie les gérants actifs du dépôt qu'un réappro est préparé et
     * attend leur confirmation/ajustement (4e événement de notification,
     * ADR 0009 §D, contrat API doc 11 §3).
     */
    private function notifierGerantsDepot(Organisation $depot, Commande $commande): void
    {
        $gerants = User::whereHas('memberships', fn ($membership) => $membership
            ->where('organisation_id', $depot->id)
            ->where('role', RoleMembership::GerantDepot->value)
            ->where('actif', true))
            ->get();

        foreach ($gerants as $gerant) {
            $this->notificateur->notifier(
                $gerant,
                TypeAlerte::ReapproPrepare,
                ['commande_uuid' => $commande->uuid],
                organisation: $depot,
                commande: $commande,
            );
        }
    }
}

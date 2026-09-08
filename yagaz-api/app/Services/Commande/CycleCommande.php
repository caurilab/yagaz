<?php

namespace App\Services\Commande;

use App\Enums\ModePaiement;
use App\Enums\OrigineCommande;
use App\Enums\RoleMembership;
use App\Enums\StatutCommande;
use App\Enums\StatutLivraison;
use App\Enums\StatutPaiement;
use App\Enums\TypeMouvementStock;
use App\Models\Commande;
use App\Models\FormatBouteille;
use App\Models\Livraison;
use App\Models\MouvementStock;
use App\Models\Organisation;
use App\Models\Site;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Centralise le cycle de vie d'une commande (contrat API doc 10, §2 et §6) :
 * transitions autorisées (422 si illégale), mouvements de stock, propagation
 * avec la livraison — toujours en transaction. La logique ne doit jamais être
 * dispersée dans les contrôleurs (doc 10, section « Machine à états »).
 */
final class CycleCommande
{
    /**
     * Commission simple, proportionnelle à la quantité commandée : règle
     * documentée (modèle éco, ADR 0004) faute d'une grille tarifaire fournie
     * par le PRD. Enregistrée à la création, jamais prélevée en v1.
     */
    private const int COMMISSION_G_PAR_BOUTEILLE = 50;

    /**
     * Une commande créée par un foyer part directement `confirmee` (doc 10, §2).
     */
    public function creerDepuisFoyer(Site $site, Organisation $depot, FormatBouteille $format, int $quantite, User $foyer): Commande
    {
        $commande = new Commande;
        $commande->forceFill([
            'origine' => OrigineCommande::Foyer,
            'demandeur_user_id' => $foyer->id,
            'demandeur_org_id' => null,
            'cible_org_id' => $depot->id,
            'site_id' => $site->id,
            'format_id' => $format->id,
            'quantite' => $quantite,
            'statut' => StatutCommande::Confirmee,
            'mode_paiement' => ModePaiement::ALaLivraison,
            'statut_paiement' => StatutPaiement::EnAttente,
            'commission_g' => $this->calculerCommission($quantite),
        ]);
        $commande->save();

        return $commande;
    }

    /**
     * Un dépôt (ou son livreur) propose une livraison à un foyer (« on vous
     * livre ? ») : la commande part `proposee`, en attente de réponse du
     * foyer destinataire (doc 10, §3 et §4).
     */
    public function proposer(Organisation $depot, Site $site, FormatBouteille $format, int $quantite): Commande
    {
        $commande = new Commande;
        $commande->forceFill([
            'origine' => OrigineCommande::Depot,
            'demandeur_user_id' => null,
            'demandeur_org_id' => $depot->id,
            'cible_org_id' => $depot->id,
            'site_id' => $site->id,
            'format_id' => $format->id,
            'quantite' => $quantite,
            'statut' => StatutCommande::Proposee,
            'mode_paiement' => ModePaiement::ALaLivraison,
            'statut_paiement' => StatutPaiement::EnAttente,
            'commission_g' => $this->calculerCommission($quantite),
        ]);
        $commande->save();

        return $commande;
    }

    /**
     * Réponse du foyer destinataire à une proposition (doc 10, §3) :
     * `accepte` ⇒ `confirmee`, refus ⇒ `annulee`. Rejette (422) toute
     * réponse sur une commande qui n'est pas (ou plus) une proposition.
     */
    public function repondre(Commande $commande, bool $accepte): Commande
    {
        abort_unless(
            $commande->statut === StatutCommande::Proposee,
            422,
            "Cette commande n'est pas (ou plus) une proposition en attente de réponse."
        );

        $commande->statut = $accepte ? StatutCommande::Confirmee : StatutCommande::Annulee;
        $commande->save();

        return $commande;
    }

    /**
     * Le dépôt prépare la commande : décrémente les bouteilles pleines du
     * stock (mouvement `vente`), refuse (422) si le stock est insuffisant ou
     * si la commande n'est pas `confirmee` (doc 10, §4 et §6).
     */
    public function preparer(Commande $commande): Commande
    {
        abort_unless(
            $commande->statut === StatutCommande::Confirmee,
            422,
            'Seule une commande confirmée peut être préparée.'
        );

        return DB::transaction(function () use ($commande) {
            $stock = Stock::where('organisation_id', $commande->cible_org_id)
                ->where('format_id', $commande->format_id)
                ->lockForUpdate()
                ->first();

            $disponibles = $stock !== null ? $stock->pleines : 0;
            abort_if($disponibles < $commande->quantite, 422, 'Stock de bouteilles pleines insuffisant pour préparer cette commande.');

            $stock->pleines -= $commande->quantite;
            $stock->save();

            MouvementStock::create([
                'stock_id' => $stock->id,
                'type' => TypeMouvementStock::Vente,
                'delta_pleines' => -$commande->quantite,
                'delta_vides' => 0,
                'livraison_id' => null,
            ]);

            $commande->statut = StatutCommande::Preparee;
            $commande->save();

            return $commande;
        });
    }

    /**
     * Le dépôt affecte (éventuellement) un livreur à une commande préparée,
     * créant la livraison `affectee` (doc 10, §4 et §6). Le livreur, s'il est
     * précisé, doit être membre `livreur` du dépôt cible (sinon 422).
     */
    public function affecterLivreur(Commande $commande, ?User $livreur): Livraison
    {
        abort_unless(
            $commande->statut === StatutCommande::Preparee,
            422,
            'La commande doit être préparée avant d’affecter un livreur.'
        );

        abort_if(
            $commande->livraisons()->exists(),
            422,
            'Une livraison existe déjà pour cette commande.'
        );

        if ($livreur !== null) {
            abort_unless(
                $commande->cibleOrg !== null && $livreur->estMembreDe($commande->cibleOrg, RoleMembership::Livreur),
                422,
                "Cet utilisateur n'est pas livreur de ce dépôt."
            );
        }

        return DB::transaction(function () use ($commande, $livreur) {
            $livraison = new Livraison;
            $livraison->forceFill([
                'commande_id' => $commande->id,
                'livreur_user_id' => $livreur?->id,
                'statut' => StatutLivraison::Affectee,
                'pleines_deposees' => $commande->quantite,
                'vides_recuperes' => 0,
                'affectee_at' => now(),
            ]);
            $livraison->save();

            return $livraison;
        });
    }

    private function calculerCommission(int $quantite): int
    {
        return $quantite * self::COMMISSION_G_PAR_BOUTEILLE;
    }
}

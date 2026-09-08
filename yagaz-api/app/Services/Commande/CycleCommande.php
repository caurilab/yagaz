<?php

namespace App\Services\Commande;

use App\Enums\ModePaiement;
use App\Enums\OrigineCommande;
use App\Enums\RoleMembership;
use App\Enums\StatutCommande;
use App\Enums\StatutLivraison;
use App\Enums\StatutPaiement;
use App\Enums\TypeAlerte;
use App\Enums\TypeMouvementStock;
use App\Models\Commande;
use App\Models\FormatBouteille;
use App\Models\Livraison;
use App\Models\MouvementStock;
use App\Models\Organisation;
use App\Models\Site;
use App\Models\Stock;
use App\Models\User;
use App\Services\Notification\Notificateur;
use Illuminate\Support\Facades\DB;

/**
 * Centralise le cycle de vie d'une commande (contrat API doc 10, §2 et §6) :
 * transitions autorisées (422 si illégale), mouvements de stock, propagation
 * avec la livraison — toujours en transaction. La logique ne doit jamais être
 * dispersée dans les contrôleurs (doc 10, section « Machine à états »).
 *
 * Notifie aussi le foyer concerné aux événements utiles (proposition de
 * livraison, commande préparée — contrat API doc 11, §3) via `Notificateur`,
 * sans jamais bloquer la transition métier si aucun destinataire n'est
 * identifiable (site sans propriétaire connu, par exemple).
 */
final class CycleCommande
{
    /**
     * Commission simple, proportionnelle à la quantité commandée : règle
     * documentée (modèle éco, ADR 0004) faute d'une grille tarifaire fournie
     * par le PRD. Enregistrée à la création, jamais prélevée en v1.
     */
    private const int COMMISSION_G_PAR_BOUTEILLE = 50;

    public function __construct(private readonly Notificateur $notificateur = new Notificateur) {}

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

        $this->notifierSiDestinataire($commande, $site, TypeAlerte::PropositionLivraison);

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
     *
     * Le contrôle de statut est fait DANS la transaction, sur la commande
     * rechargée sous `lockForUpdate` : deux préparations concurrentes de la
     * même commande se sérialisent sur le verrou de ligne, la seconde voit le
     * statut déjà `preparee` et est refusée — sans quoi elles pourraient
     * toutes deux lire `confirmee` et décrémenter le stock deux fois (TOCTOU,
     * audit sécurité Phase 4, [MOYEN]).
     */
    public function preparer(Commande $commande): Commande
    {
        return DB::transaction(function () use ($commande) {
            $commandeVerrouillee = Commande::whereKey($commande->id)->lockForUpdate()->firstOrFail();

            abort_unless(
                $commandeVerrouillee->statut === StatutCommande::Confirmee,
                422,
                'Seule une commande confirmée peut être préparée.'
            );

            $stock = Stock::where('organisation_id', $commandeVerrouillee->cible_org_id)
                ->where('format_id', $commandeVerrouillee->format_id)
                ->lockForUpdate()
                ->first();

            $disponibles = $stock !== null ? $stock->pleines : 0;
            abort_if($disponibles < $commandeVerrouillee->quantite, 422, 'Stock de bouteilles pleines insuffisant pour préparer cette commande.');

            $stock->pleines -= $commandeVerrouillee->quantite;
            $stock->save();

            MouvementStock::create([
                'stock_id' => $stock->id,
                'type' => TypeMouvementStock::Vente,
                'delta_pleines' => -$commandeVerrouillee->quantite,
                'delta_vides' => 0,
                'livraison_id' => null,
            ]);

            $commandeVerrouillee->statut = StatutCommande::Preparee;
            $commandeVerrouillee->save();

            $this->notifierSiDestinataire($commandeVerrouillee, $commandeVerrouillee->site, TypeAlerte::CommandePreparee);

            return $commandeVerrouillee;
        });
    }

    /**
     * Le dépôt affecte (éventuellement) un livreur à une commande préparée,
     * créant la livraison `affectee` (doc 10, §4 et §6). Le livreur, s'il est
     * précisé, doit être membre `livreur` du dépôt cible (sinon 422).
     *
     * Le statut de la commande et l'existence d'une livraison sont vérifiés
     * DANS la transaction, sur la commande rechargée sous `lockForUpdate` :
     * deux affectations concurrentes se sérialisent sur le verrou de ligne,
     * la seconde voit la livraison déjà créée par la première et est refusée
     * (TOCTOU, audit sécurité Phase 4, [MOYEN]). Un index unique sur
     * `livraisons.commande_id` (migration `add_unique_commande_id_to_livraisons`)
     * fait office de garde-fou base de données en dernier recours.
     */
    public function affecterLivreur(Commande $commande, ?User $livreur): Livraison
    {
        if ($livreur !== null) {
            abort_unless(
                $commande->cibleOrg !== null && $livreur->estMembreDe($commande->cibleOrg, RoleMembership::Livreur),
                422,
                "Cet utilisateur n'est pas livreur de ce dépôt."
            );
        }

        return DB::transaction(function () use ($commande, $livreur) {
            $commandeVerrouillee = Commande::whereKey($commande->id)->lockForUpdate()->firstOrFail();

            abort_unless(
                $commandeVerrouillee->statut === StatutCommande::Preparee,
                422,
                'La commande doit être préparée avant d’affecter un livreur.'
            );

            abort_if(
                $commandeVerrouillee->livraisons()->exists(),
                422,
                'Une livraison existe déjà pour cette commande.'
            );

            $livraison = new Livraison;
            $livraison->forceFill([
                'commande_id' => $commandeVerrouillee->id,
                'livreur_user_id' => $livreur?->id,
                'statut' => StatutLivraison::Affectee,
                'pleines_deposees' => $commandeVerrouillee->quantite,
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

    /**
     * Notifie le foyer destinataire d'une commande, s'il est identifiable
     * (contrat API doc 11, §3). N'échoue jamais faute de destinataire — la
     * transition métier reste valide même sans notification possible.
     */
    private function notifierSiDestinataire(Commande $commande, ?Site $site, TypeAlerte $type): void
    {
        $destinataire = $this->notificateur->resoudreDestinataireFoyer($commande);

        if ($destinataire === null) {
            return;
        }

        $this->notificateur->notifier(
            $destinataire,
            $type,
            ['commande_uuid' => $commande->uuid],
            commande: $commande,
            site: $site,
        );
    }
}

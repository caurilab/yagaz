<?php

namespace App\Services\Commande;

use App\Enums\StatutCommande;
use App\Enums\StatutLivraison;
use App\Enums\TypeAlerte;
use App\Enums\TypeMouvementStock;
use App\Models\Commande;
use App\Models\Livraison;
use App\Models\MouvementStock;
use App\Models\Stock;
use App\Services\Notification\Notificateur;
use Illuminate\Support\Facades\DB;

/**
 * Centralise le cycle de vie d'une livraison (contrat API doc 10, §5 et §6) :
 * transitions ordonnées (`affectee → en_route → livree → vide_recupere`,
 * jamais en arrière, rejet 422 sinon), propagation du statut sur la commande,
 * mouvement de stock au retour des vides — toujours en transaction.
 *
 * Notifie aussi le foyer du changement de statut de sa commande (contrat API
 * doc 11, §3), sans jamais bloquer la transition si aucun destinataire n'est
 * identifiable.
 */
final class CycleLivraison
{
    public function __construct(private readonly Notificateur $notificateur = new Notificateur) {}

    /**
     * Ordre des statuts, pour n'autoriser que la transition vers le suivant
     * immédiat (doc 10, §5 : « Transitions ordonnées »).
     *
     * @var array<string, int>
     */
    private const array ORDRE = [
        'affectee' => 0,
        'en_route' => 1,
        'livree' => 2,
        'vide_recupere' => 3,
    ];

    public function changerStatut(Livraison $livraison, StatutLivraison $nouveauStatut, ?int $videsRecuperes = null): Livraison
    {
        $rangActuel = self::ORDRE[$livraison->statut->value];
        $rangSuivant = self::ORDRE[$nouveauStatut->value];

        abort_unless(
            $rangSuivant === $rangActuel + 1,
            422,
            'Transition de statut de livraison invalide.'
        );

        return DB::transaction(function () use ($livraison, $nouveauStatut, $videsRecuperes) {
            $commande = $livraison->commande;

            match ($nouveauStatut) {
                StatutLivraison::EnRoute => $this->passerEnRoute($livraison, $commande),
                StatutLivraison::Livree => $this->passerLivree($livraison, $commande),
                StatutLivraison::VideRecupere => $this->passerVideRecupere($livraison, $commande, $videsRecuperes),
                StatutLivraison::Affectee => abort(422, 'Transition de statut de livraison invalide.'),
            };

            return $livraison->fresh();
        });
    }

    /**
     * `en_route` ⇒ la commande passe `en_livraison` (doc 10, §2 et §6).
     */
    private function passerEnRoute(Livraison $livraison, Commande $commande): void
    {
        $livraison->statut = StatutLivraison::EnRoute;
        $livraison->en_route_at = now();
        $livraison->save();

        $commande->statut = StatutCommande::EnLivraison;
        $commande->save();

        $this->notifierSiDestinataire($commande, TypeAlerte::CommandeEnLivraison);
    }

    /**
     * `livree` ⇒ la commande passe `livree` (doc 10, §2 et §6).
     */
    private function passerLivree(Livraison $livraison, Commande $commande): void
    {
        $livraison->statut = StatutLivraison::Livree;
        $livraison->livree_at = now();
        $livraison->save();

        $commande->statut = StatutCommande::Livree;
        $commande->save();

        $this->notifierSiDestinataire($commande, TypeAlerte::CommandeLivree);
    }

    /**
     * `vide_recupere` ⇒ incrémente les `vides` du stock du dépôt, mouvement
     * `retour_vide` tracé avec la livraison en référence (doc 10, §6). À
     * défaut de précision, autant de vides récupérés que de pleines livrées.
     *
     * Refuse (422) `vides_recuperes > commande.quantite` : la borne haute du
     * FormRequest est large et ne protège pas vraiment, un livreur pourrait
     * sinon gonfler arbitrairement le stock `vides` d'un dépôt (audit
     * sécurité Phase 4, [MOYEN]). Une livraison ne peut jamais rapporter plus
     * de vides que de pleines commandées.
     */
    private function passerVideRecupere(Livraison $livraison, Commande $commande, ?int $videsRecuperes): void
    {
        abort_if(
            $videsRecuperes !== null && $videsRecuperes > $commande->quantite,
            422,
            'Le nombre de vides récupérés ne peut pas dépasser la quantité commandée.'
        );

        $quantite = $videsRecuperes ?? $commande->quantite;

        $livraison->statut = StatutLivraison::VideRecupere;
        $livraison->vide_recupere_at = now();
        $livraison->vides_recuperes = $quantite;
        $livraison->save();

        $stock = Stock::where('organisation_id', $commande->cible_org_id)
            ->where('format_id', $commande->format_id)
            ->lockForUpdate()
            ->first();

        if ($stock === null) {
            return;
        }

        $stock->vides += $quantite;
        $stock->save();

        MouvementStock::create([
            'stock_id' => $stock->id,
            'type' => TypeMouvementStock::RetourVide,
            'delta_pleines' => 0,
            'delta_vides' => $quantite,
            'livraison_id' => $livraison->id,
        ]);
    }

    /**
     * Notifie le foyer destinataire d'une commande, s'il est identifiable
     * (contrat API doc 11, §3). N'échoue jamais faute de destinataire — la
     * transition métier reste valide même sans notification possible.
     */
    private function notifierSiDestinataire(Commande $commande, TypeAlerte $type): void
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
            site: $commande->site,
        );
    }
}

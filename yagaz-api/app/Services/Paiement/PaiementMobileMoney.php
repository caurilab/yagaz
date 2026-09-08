<?php

namespace App\Services\Paiement;

use App\Contracts\Payment\PaymentProvider;
use App\Enums\ModePaiement;
use App\Enums\StatutCommande;
use App\Enums\StatutPaiement;
use App\Models\Commande;
use App\Models\Paiement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orchestre le flux de paiement Mobile Money d'une commande (ADR 0010, v2
 * brique 1) derrière l'abstraction `PaymentProvider` — analogue à
 * `CycleCommande` pour le cycle de vie d'une commande : la logique ne doit
 * jamais être dispersée dans le contrôleur.
 *
 * Sécurité (ADR 0010, non négociable, audité) : le statut d'un paiement
 * n'est JAMAIS déduit d'une entrée client — seuls `traiterWebhook` (après
 * `verifierNotification`) et `reconcilier` (interrogation active du
 * provider) le font avancer vers `regle`/`echoue`/`expire`.
 */
final class PaiementMobileMoney
{
    /**
     * Prix unitaire simple, faute d'une grille tarifaire fournie par le PRD
     * (même limitation documentée que la commission, ADR 0004/0010) : à
     * remplacer par un vrai catalogue de prix quand il sera disponible, sans
     * changer le reste du flux.
     */
    private const int PRIX_XOF_PAR_BOUTEILLE = 6_500;

    /**
     * Un paiement `initie` n'est éligible à la réconciliation active que
     * passé ce délai — évite de court-circuiter un webhook encore en transit.
     */
    private const int RECONCILIATION_AGE_MINUTES = 5;

    /**
     * Durée de vie d'une intention de paiement `initie` (push USSD/lien,
     * durée usuelle côté opérateur Mobile Money) : passé ce délai sans
     * webhook, une nouvelle initiation la remplace au lieu de la réutiliser
     * (audit sécurité, [MOYEN] double-facturation — évite de bloquer
     * indéfiniment un foyer derrière une intention morte).
     */
    private const int INITIATION_EXPIRATION_MINUTES = 15;

    public function __construct(private readonly PaymentProvider $provider) {}

    /**
     * Le foyer propriétaire initie un paiement Mobile Money pour une
     * commande `confirmee` (ADR 0010, §Flux, point 1) : crée le `Paiement`
     * (`initie`), appelle le provider, fait passer la commande en
     * `mode_paiement = mobile_money` / `statut_paiement = initie`.
     *
     * Garde-fous contre la double-facturation (audit sécurité, [MOYEN]) :
     * paiement et livraison étant découplés, une commande reste `confirmee`
     * après avoir été réglée — on refuse donc toute ré-initiation dès que
     * `statut_paiement = regle`, et on réutilise une intention `initie` déjà
     * en cours (non expirée) plutôt que d'en empiler une seconde chez le
     * provider.
     *
     * @return array{paiement: Paiement, intention: array<string, mixed>}
     */
    public function initier(Commande $commande): array
    {
        return DB::transaction(function () use ($commande) {
            $commandeVerrouillee = Commande::whereKey($commande->id)->lockForUpdate()->firstOrFail();

            abort_unless(
                $commandeVerrouillee->statut === StatutCommande::Confirmee,
                422,
                'Seule une commande confirmée peut être payée.'
            );

            abort_if(
                $commandeVerrouillee->statut_paiement === StatutPaiement::Regle,
                422,
                'Cette commande est déjà réglée.'
            );

            $paiementInitieExistant = Paiement::where('commande_id', $commandeVerrouillee->id)
                ->where('statut', StatutPaiement::Initie)
                ->orderByDesc('id')
                ->first();

            if ($paiementInitieExistant !== null && ! $this->initiationExpiree($paiementInitieExistant)) {
                // Intention encore valide : on la réutilise telle quelle,
                // sans rappeler le provider (pas de second push USSD/lien
                // concurrent pour la même commande).
                return [
                    'paiement' => $paiementInitieExistant,
                    'intention' => [
                        'reference' => $paiementInitieExistant->reference,
                        'statut' => $paiementInitieExistant->statut->value,
                        'instructions' => 'Une intention de paiement est déjà en cours pour cette commande.',
                    ],
                ];
            }

            if ($paiementInitieExistant !== null) {
                // Intention expirée : elle n'engage plus rien côté provider,
                // on la clôt avant d'en créer une nouvelle.
                $paiementInitieExistant->forceFill(['statut' => StatutPaiement::Expire]);
                $paiementInitieExistant->save();
            }

            $paiement = new Paiement;
            $paiement->forceFill([
                'commande_id' => $commandeVerrouillee->id,
                'provider' => (string) config('paiement.provider'),
                'reference' => null,
                'montant' => $this->calculerMontant($commandeVerrouillee->quantite),
                'devise' => (string) config('paiement.devise'),
                'statut' => StatutPaiement::Initie,
            ]);
            $paiement->save();

            $intention = $this->provider->initier($paiement);

            if (! empty($intention['reference'])) {
                $paiement->forceFill(['reference' => (string) $intention['reference']]);
                $paiement->save();
            }

            $commandeVerrouillee->forceFill([
                'mode_paiement' => ModePaiement::MobileMoney,
                'statut_paiement' => StatutPaiement::Initie,
            ]);
            $commandeVerrouillee->save();

            return ['paiement' => $paiement, 'intention' => $intention];
        });
    }

    /**
     * Traite une notification webhook (ADR 0010, §Flux, point 2 ;
     * §Sécurité) : signature obligatoire, idempotence stricte via l'unique
     * `(provider, reference)`, montant/devise vérifiés contre le `Paiement`
     * avant de faire avancer commande + paiement vers `regle`/`echoue`.
     *
     * @param  array<string, mixed>  $payload
     */
    public function traiterWebhook(string $provider, array $payload, ?string $signature): void
    {
        abort_unless(
            $provider === (string) config('paiement.provider'),
            400,
            'Provider de paiement inconnu.'
        );

        abort_unless(
            $this->provider->verifierNotification($payload, $signature),
            400,
            'Signature de notification invalide.'
        );

        $resultat = $this->provider->extraireResultat($payload);

        abort_if($resultat['reference'] === '', 400, 'Référence de paiement manquante.');

        $statutNotifie = StatutPaiement::tryFrom($resultat['statut']);

        abort_if(
            $statutNotifie === null || ! in_array($statutNotifie, [StatutPaiement::Regle, StatutPaiement::Echoue], true),
            400,
            'Statut de notification invalide.'
        );

        DB::transaction(function () use ($provider, $resultat, $statutNotifie) {
            $paiement = Paiement::where('provider', $provider)
                ->where('reference', $resultat['reference'])
                ->lockForUpdate()
                ->first();

            if ($paiement === null) {
                Log::warning('yagaz.paiement.webhook.reference_inconnue', [
                    'provider' => $provider,
                    'reference' => $resultat['reference'],
                ]);

                abort(400, 'Paiement inconnu pour cette référence.');
            }

            // Idempotence stricte (ADR 0010 §Sécurité) : un rejeu de la même
            // notification sur un paiement déjà résolu ne modifie rien et ne
            // crédite jamais deux fois — la référence UNIQUE (provider,
            // reference) garantit qu'il ne peut s'agir que de ce même paiement.
            if ($paiement->statut !== StatutPaiement::Initie) {
                return;
            }

            if ($paiement->montant !== $resultat['montant'] || $paiement->devise !== $resultat['devise']) {
                Log::warning('yagaz.paiement.webhook.montant_incoherent', [
                    'provider' => $provider,
                    'reference' => $resultat['reference'],
                    'paiement_montant' => $paiement->montant,
                    'paiement_devise' => $paiement->devise,
                    'notifie_montant' => $resultat['montant'],
                    'notifie_devise' => $resultat['devise'],
                ]);

                abort(400, 'Montant ou devise incohérent avec le paiement.');
            }

            $this->appliquerStatut($paiement, $statutNotifie);
        });
    }

    /**
     * Réconcilie les paiements `initie` anciens auprès du provider (ADR
     * 0010, §Flux, point 3 ; commande `paiements:reconcilier`) : seule cette
     * interrogation active (ou le webhook vérifié) fait foi.
     *
     * @return int Nombre de paiements mis à jour.
     */
    public function reconcilier(): int
    {
        $seuil = now()->subMinutes(self::RECONCILIATION_AGE_MINUTES);

        $paiements = Paiement::where('statut', StatutPaiement::Initie)
            ->whereNotNull('reference')
            ->where('created_at', '<=', $seuil)
            ->get();

        $miseAJour = 0;

        foreach ($paiements as $paiement) {
            $statutProvider = StatutPaiement::tryFrom($this->provider->statut($paiement->reference));

            if ($statutProvider === null
                || ! in_array($statutProvider, [StatutPaiement::Regle, StatutPaiement::Echoue, StatutPaiement::Expire], true)) {
                continue;
            }

            $applique = DB::transaction(function () use ($paiement, $statutProvider) {
                $paiementVerrouille = Paiement::whereKey($paiement->id)->lockForUpdate()->first();

                if ($paiementVerrouille === null || $paiementVerrouille->statut !== StatutPaiement::Initie) {
                    return false;
                }

                $this->appliquerStatut($paiementVerrouille, $statutProvider);

                return true;
            });

            if ($applique) {
                $miseAJour++;
            }
        }

        return $miseAJour;
    }

    private function calculerMontant(int $quantite): int
    {
        return $quantite * self::PRIX_XOF_PAR_BOUTEILLE;
    }

    /**
     * Une intention `initie` plus vieille que `INITIATION_EXPIRATION_MINUTES`
     * est considérée morte côté provider : elle peut être remplacée par une
     * nouvelle initiation plutôt que réutilisée.
     */
    private function initiationExpiree(Paiement $paiement): bool
    {
        return $paiement->created_at === null
            || $paiement->created_at->lt(now()->subMinutes(self::INITIATION_EXPIRATION_MINUTES));
    }

    /**
     * Fait avancer un paiement (déjà verrouillé par l'appelant) et la
     * commande associée vers le statut résolu — commun au webhook et à la
     * réconciliation. La commission (déjà enregistrée à la création de la
     * commande, ADR 0004) n'a rien à recalculer : le rapprochement consiste
     * simplement à ne jamais la toucher hors de ce point de résolution.
     */
    private function appliquerStatut(Paiement $paiement, StatutPaiement $statut): void
    {
        $paiement->forceFill(['statut' => $statut]);
        $paiement->save();

        $commande = Commande::whereKey($paiement->commande_id)->lockForUpdate()->first();

        if ($commande !== null) {
            $commande->forceFill(['statut_paiement' => $statut]);
            $commande->save();
        }
    }
}

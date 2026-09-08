<?php

namespace App\Contracts\Payment;

use App\Models\Paiement;

/**
 * Contrat d'un opérateur de paiement Mobile Money (ADR 0010, v2 brique 1) :
 * initier une intention de paiement, vérifier/traiter une notification
 * (webhook), consulter un statut pour la réconciliation. L'implémentation
 * par défaut (`App\Services\Payment\SimulateurPaiement`) simule le cycle
 * sans appel réseau réel ; un agrégateur réel (Orange Money, MTN MoMo, Moov
 * Money, Wave via un PSP fronting) se substituera à elle — liée dans
 * `AppServiceProvider` — sans toucher au cycle de commande ni aux
 * contrôleurs.
 *
 * Sécurité (ADR 0010, non négociable) : le statut d'un paiement ne doit
 * JAMAIS être déduit d'une donnée non vérifiée par `verifierNotification`.
 */
interface PaymentProvider
{
    /**
     * Initie un paiement auprès du provider pour ce `Paiement` (déjà créé en
     * base, statut `initie`) : renvoie au minimum une référence provider et
     * l'intention (ex. push USSD, lien de paiement) à afficher au foyer.
     *
     * @return array<string, mixed>
     */
    public function initier(Paiement $paiement): array;

    /**
     * Vérifie l'authenticité d'une notification webhook (signature calculée
     * à partir du corps et d'un secret partagé). Ne doit jamais lever
     * d'exception sur un payload malformé : renvoie `false`.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifierNotification(array $payload, ?string $signature): bool;

    /**
     * Extrait du payload (déjà vérifié par `verifierNotification`) les
     * informations nécessaires au rapprochement : référence, statut,
     * montant, devise.
     *
     * @param  array<string, mixed>  $payload
     * @return array{reference: string, statut: string, montant: int, devise: string}
     */
    public function extraireResultat(array $payload): array;

    /**
     * Interroge le provider pour le statut courant d'une référence (pour la
     * réconciliation des paiements `initie` anciens, commande
     * `paiements:reconcilier`). Renvoie une valeur de `StatutPaiement`.
     */
    public function statut(string $reference): string;
}

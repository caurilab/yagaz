<?php

namespace App\Console\Commands;

use App\Services\Paiement\PaiementMobileMoney;
use Illuminate\Console\Command;

/**
 * Réconciliation active des paiements Mobile Money (ADR 0010, §Flux point 3) :
 * pour les paiements `initie` anciens, interroge `PaymentProvider::statut` et
 * met à jour leur statut — rattrape les notifications webhook jamais reçues.
 * Pas de planification (cron) ici, juste la commande.
 */
class ReconcilierPaiements extends Command
{
    /**
     * @var string
     */
    protected $signature = 'paiements:reconcilier';

    /**
     * @var string
     */
    protected $description = 'Interroge le provider de paiement pour les paiements `initie` anciens et met à jour leur statut';

    public function handle(PaiementMobileMoney $paiementMobileMoney): int
    {
        $nombre = $paiementMobileMoney->reconcilier();

        $this->info("{$nombre} paiement(s) réconcilié(s).");

        return self::SUCCESS;
    }
}

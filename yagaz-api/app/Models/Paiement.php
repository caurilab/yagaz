<?php

namespace App\Models;

use App\Enums\StatutPaiement;
use App\Traits\HasUuid;
use Database\Factories\PaiementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne du ledger de paiement Mobile Money d'une commande (ADR 0010, v2
 * brique 1 ; doc 07, §6). Aucune colonne n'est mass-assignable : un
 * `Paiement` n'est jamais créé/modifié qu'en code serveur (`forceFill`/
 * `forceCreate`) — initiation via `PaymentProvider::initier`, résolution du
 * statut via le webhook vérifié ou la réconciliation (ADR 0005, ADR 0010
 * §Sécurité), jamais depuis une entrée client.
 */
#[Fillable([])]
class Paiement extends Model
{
    /** @use HasFactory<PaiementFactory> */
    use HasFactory, HasUuid;

    protected function casts(): array
    {
        return [
            'statut' => StatutPaiement::class,
        ];
    }

    /**
     * @return BelongsTo<Commande, $this>
     */
    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class);
    }
}

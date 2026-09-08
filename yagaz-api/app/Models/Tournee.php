<?php

namespace App\Models;

use App\Enums\StatutTournee;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Regroupe des livraisons d'un mandataire (ou dépôt) pour une journée —
 * doc 07, §6 `tournees`.
 */
#[Guarded([])]
class Tournee extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'statut' => StatutTournee::class,
        ];
    }

    /**
     * @return BelongsTo<Organisation, $this>
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function livreur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'livreur_user_id');
    }

    /**
     * @return HasMany<Livraison, $this>
     */
    public function livraisons(): HasMany
    {
        return $this->hasMany(Livraison::class);
    }
}

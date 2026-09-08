<?php

namespace App\Models;

use App\Enums\CanalAlerte;
use App\Enums\StatutAlerte;
use App\Enums\TypeAlerte;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trace des franchissements de seuil et propositions, pour éviter le
 * re-spam — doc 07, §8 `alertes`.
 */
#[Guarded([])]
class Alerte extends Model
{
    /**
     * Uniquement `created_at` (pas d'`updated_at` au doc 07).
     */
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => TypeAlerte::class,
            'statut' => StatutAlerte::class,
            'canal' => CanalAlerte::class,
        ];
    }

    /**
     * @return BelongsTo<Bouteille, $this>
     */
    public function bouteille(): BelongsTo
    {
        return $this->belongsTo(Bouteille::class);
    }

    /**
     * Organisation concernée par une alerte de tension de stock.
     *
     * @return BelongsTo<Organisation, $this>
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}

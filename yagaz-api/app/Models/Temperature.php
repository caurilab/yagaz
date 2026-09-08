<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Chaque température de cuisine reçue d'un plateau (ADR 0011) — série
 * temporelle jumelle de `Mesure` (doc 08 §5).
 *
 * Comme `Mesure` : pas de `created_at`/`updated_at` standard (la table porte
 * `mesure_at`/`recu_at`), et clé composite (plateau_id, mesure_at, seq) —
 * contrainte des hypertables TimescaleDB. On désactive donc la clé primaire
 * auto-incrémentée d'Eloquent.
 */
#[Guarded([])]
class Temperature extends Model
{
    /**
     * Pas de created_at/updated_at Laravel : la table porte ses propres
     * horodatages métier (mesure_at, recu_at).
     */
    public $timestamps = false;

    /**
     * Clé primaire composite (voir migration) : pas d'`id` auto-incrémenté.
     */
    protected $primaryKey = null;

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'mesure_at' => 'datetime',
            'recu_at' => 'datetime',
            'temp_c' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Plateau, $this>
     */
    public function plateau(): BelongsTo
    {
        return $this->belongsTo(Plateau::class);
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}

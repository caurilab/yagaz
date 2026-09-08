<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Chaque pesée reçue d'un plateau (ADR 0003) — doc 07, §5 `mesures`.
 *
 * Écart au doc 07 : la table n'a pas les colonnes `created_at`/`updated_at`
 * standard de Laravel (elle porte `mesure_at`/`recu_at`), et sa clé primaire
 * technique est un `id` bigint auto-incrémenté plutôt que la paire composite
 * (plateau_id, mesure_at) du doc — voir la migration `create_mesures_table`.
 */
#[Guarded([])]
class Mesure extends Model
{
    /**
     * Pas de created_at/updated_at Laravel : la table porte ses propres
     * horodatages métier (mesure_at, recu_at).
     */
    public $timestamps = false;

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
     * Bouteille résolue à l'ingestion (peut être nulle si non déterminée).
     *
     * @return BelongsTo<Bouteille, $this>
     */
    public function bouteille(): BelongsTo
    {
        return $this->belongsTo(Bouteille::class);
    }
}

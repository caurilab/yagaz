<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Chaque pesée reçue d'un plateau (ADR 0003) — doc 07, §5 `mesures`.
 *
 * La table n'a pas les colonnes `created_at`/`updated_at` standard de Laravel
 * (elle porte `mesure_at`/`recu_at`), et sa clé est composite
 * (plateau_id, mesure_at, seq) — contrainte des hypertables TimescaleDB.
 * On désactive donc la clé primaire auto-incrémentée d'Eloquent : la table
 * est alimentée par l'ingestion et lue par requêtes, jamais par `find($id)`.
 */
#[Guarded([])]
class Mesure extends Model
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
     * Bouteille résolue à l'ingestion (peut être nulle si non déterminée).
     *
     * @return BelongsTo<Bouteille, $this>
     */
    public function bouteille(): BelongsTo
    {
        return $this->belongsTo(Bouteille::class);
    }
}

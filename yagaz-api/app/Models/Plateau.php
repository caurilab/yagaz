<?php

namespace App\Models;

use App\Enums\AlimPlateau;
use App\Enums\StatutPlateau;
use Database\Factories\PlateauFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Le capteur physique (plateau de pesée) — doc 07, §4 `plateaux`.
 */
#[Guarded([])]
#[Hidden(['secret_hash'])]
class Plateau extends Model
{
    /** @use HasFactory<PlateauFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'statut' => StatutPlateau::class,
            'alim' => AlimPlateau::class,
            'dernier_vu_at' => 'datetime',
        ];
    }

    /**
     * Site où le plateau est installé (null si en stock/non posé).
     *
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Bouteilles ayant été posées sur ce plateau.
     *
     * @return HasMany<Bouteille, $this>
     */
    public function bouteilles(): HasMany
    {
        return $this->hasMany(Bouteille::class);
    }

    /**
     * Mesures brutes reçues de ce plateau.
     *
     * @return HasMany<Mesure, $this>
     */
    public function mesures(): HasMany
    {
        return $this->hasMany(Mesure::class);
    }
}

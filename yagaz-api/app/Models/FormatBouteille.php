<?php

namespace App\Models;

use Database\Factories\FormatBouteilleFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Référentiel des formats/marques de bouteille (B6, B12, B24…), avec tare
 * nominale — doc 07, §4 `formats_bouteille`.
 */
#[Guarded([])]
class FormatBouteille extends Model
{
    /** @use HasFactory<FormatBouteilleFactory> */
    use HasFactory;

    protected $table = 'formats_bouteille';

    /**
     * @return HasMany<Bouteille, $this>
     */
    public function bouteilles(): HasMany
    {
        return $this->hasMany(Bouteille::class, 'format_id');
    }

    /**
     * @return HasMany<Stock, $this>
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class, 'format_id');
    }

    /**
     * @return HasMany<Commande, $this>
     */
    public function commandes(): HasMany
    {
        return $this->hasMany(Commande::class, 'format_id');
    }
}

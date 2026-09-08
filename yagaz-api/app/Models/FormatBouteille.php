<?php

namespace App\Models;

use Database\Factories\FormatBouteilleFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
     * Marque référentielle (couleur, etc). Nommée `marqueRef` — et non
     * `marque` — pour ne pas entrer en collision avec la colonne `marque`
     * (string, conservée pour compat) : Eloquent ferait toujours primer
     * l'attribut de colonne sur la relation pour un accès magique `->marque`.
     *
     * @return BelongsTo<Marque, $this>
     */
    public function marqueRef(): BelongsTo
    {
        return $this->belongsTo(Marque::class, 'marque_id');
    }

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

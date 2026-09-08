<?php

namespace App\Models;

use Database\Factories\MarqueFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Référentiel des marques de gaz (Oryx, Total, Petro Ivoire…), avec un code
 * couleur hex pour le sélecteur à l'enregistrement d'une bouteille.
 */
#[Guarded([])]
class Marque extends Model
{
    /** @use HasFactory<MarqueFactory> */
    use HasFactory;

    /**
     * @return HasMany<FormatBouteille, $this>
     */
    public function formats(): HasMany
    {
        return $this->hasMany(FormatBouteille::class, 'marque_id');
    }
}

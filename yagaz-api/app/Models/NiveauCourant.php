<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Table de cache du dernier état par bouteille, pour un affichage instantané
 * et le fonctionnement hors ligne côté app — doc 07, §5 `niveaux_courants`.
 */
#[Guarded([])]
class NiveauCourant extends Model
{
    protected $table = 'niveaux_courants';

    protected $primaryKey = 'bouteille_id';

    protected $keyType = 'int';

    public $incrementing = false;

    /**
     * Seule `calcule_at` fait office d'horodatage ; pas de created_at/updated_at.
     */
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'calcule_at' => 'datetime',
            'debit_g_par_h' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Bouteille, $this>
     */
    public function bouteille(): BelongsTo
    {
        return $this->belongsTo(Bouteille::class);
    }
}

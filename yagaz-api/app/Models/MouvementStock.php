<?php

namespace App\Models;

use App\Enums\TypeMouvementStock;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Journal des variations de stock (vente, retour vide, réappro, ajustement),
 * pour l'historique et l'audit — doc 07, §7 `mouvements_stock`.
 */
#[Guarded([])]
class MouvementStock extends Model
{
    protected $table = 'mouvements_stock';

    /**
     * Uniquement `created_at` (pas de mise à jour d'un mouvement de stock,
     * c'est un journal immuable).
     */
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => TypeMouvementStock::class,
        ];
    }

    /**
     * @return BelongsTo<Stock, $this>
     */
    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    /**
     * Livraison à l'origine du mouvement, si applicable.
     *
     * @return BelongsTo<Livraison, $this>
     */
    public function livraison(): BelongsTo
    {
        return $this->belongsTo(Livraison::class);
    }
}

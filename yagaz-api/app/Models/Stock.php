<?php

namespace App\Models;

use Database\Factories\StockFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * État du stock d'une organisation (dépôt surtout), par format, plein et
 * vide — doc 07, §7 `stocks`.
 */
#[Guarded([])]
class Stock extends Model
{
    /** @use HasFactory<StockFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Organisation, $this>
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * @return BelongsTo<FormatBouteille, $this>
     */
    public function format(): BelongsTo
    {
        return $this->belongsTo(FormatBouteille::class, 'format_id');
    }

    /**
     * @return HasMany<MouvementStock, $this>
     */
    public function mouvements(): HasMany
    {
        return $this->hasMany(MouvementStock::class);
    }
}

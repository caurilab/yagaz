<?php

namespace App\Models;

use Database\Factories\StockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * État du stock d'une organisation (dépôt surtout), par format, plein et
 * vide — doc 07, §7 `stocks`.
 *
 * `$fillable` explicite (audit sécurité Phase 4, [INFO] `$fillable`
 * explicite, ADR 0005 pt 3) : `organisation_id` et `format_id` en sont
 * volontairement exclus — dérivés côté serveur de l'organisation/format de la
 * route (déjà contrôlés par la policy), jamais du corps de la requête. Posés
 * via `forceFill`/affectation directe dans `DepotStockController` et
 * `CycleCommande`/`CycleLivraison`.
 */
#[Fillable(['pleines', 'vides', 'seuil_plein_bas'])]
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

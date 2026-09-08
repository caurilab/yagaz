<?php

namespace App\Models;

use App\Enums\StatutLivraison;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * L'exécution physique d'une commande par un livreur — doc 07, §6 `livraisons`.
 *
 * `$fillable` explicite (audit sécurité Phase 4, [INFO] `$fillable`
 * explicite, ADR 0005 pt 3) : entièrement verrouillée (`fillable = []`),
 * comme `Membership`/`SiteAcces`. Une livraison n'est créée que par
 * `CycleCommande::affecterLivreur` (`forceFill`) et n'est mise à jour que par
 * `CycleLivraison` (affectation directe de propriété) : aucune colonne n'a
 * légitimement besoin d'être mass-assignable.
 */
#[Fillable([])]
class Livraison extends Model
{
    protected function casts(): array
    {
        return [
            'statut' => StatutLivraison::class,
            'affectee_at' => 'datetime',
            'en_route_at' => 'datetime',
            'livree_at' => 'datetime',
            'vide_recupere_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Commande, $this>
     */
    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function livreur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'livreur_user_id');
    }

    /**
     * @return BelongsTo<Tournee, $this>
     */
    public function tournee(): BelongsTo
    {
        return $this->belongsTo(Tournee::class);
    }

    /**
     * Mouvements de stock générés par cette livraison.
     *
     * @return HasMany<MouvementStock, $this>
     */
    public function mouvementsStock(): HasMany
    {
        return $this->hasMany(MouvementStock::class);
    }
}

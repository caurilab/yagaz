<?php

namespace App\Models;

use App\Traits\HasUuid;
use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Une adresse physique où se trouvent des bouteilles — doc 07, §3 `sites`.
 *
 * `$fillable` explicite (audit sécurité, [INFO] `$fillable` explicite) :
 * seuls les champs saisissables par le client via l'API. `cree_par` en est
 * volontairement exclu — dérivé côté serveur de l'utilisateur authentifié
 * (ADR 0005), posé via `forceFill` dans `SiteController::store()`.
 */
#[Fillable(['nom', 'adresse', 'lat', 'lng'])]
class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory, HasUuid;

    /**
     * Créateur du site.
     *
     * @return BelongsTo<User, $this>
     */
    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    /**
     * Droits d'accès posés sur ce site.
     *
     * @return HasMany<SiteAcces, $this>
     */
    public function siteAcces(): HasMany
    {
        return $this->hasMany(SiteAcces::class);
    }

    /**
     * Utilisateurs ayant accès à ce site (via site_acces).
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'site_acces')
            ->withPivot('niveau')
            ->withTimestamps();
    }

    /**
     * Bouteilles suivies sur ce site.
     *
     * @return HasMany<Bouteille, $this>
     */
    public function bouteilles(): HasMany
    {
        return $this->hasMany(Bouteille::class);
    }

    /**
     * Plateaux installés sur ce site.
     *
     * @return HasMany<Plateau, $this>
     */
    public function plateaux(): HasMany
    {
        return $this->hasMany(Plateau::class);
    }

    /**
     * Commandes livrées à ce site.
     *
     * @return HasMany<Commande, $this>
     */
    public function commandes(): HasMany
    {
        return $this->hasMany(Commande::class);
    }

    /**
     * Livreur habituel désigné pour ce site.
     *
     * @return HasOne<LivreurHabituel, $this>
     */
    public function livreurHabituel(): HasOne
    {
        return $this->hasOne(LivreurHabituel::class);
    }
}

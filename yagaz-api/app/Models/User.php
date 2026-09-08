<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\HasUuid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Le compte de connexion (doc 07, §2 `users`). Un humain = un compte, quel
 * que soit son ou ses rôles (memberships pro, ou foyer sans organisation).
 */
#[Fillable(['name', 'email', 'password', 'telephone', 'langue'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuid, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Rattachements de l'utilisateur à des organisations, avec rôle.
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Organisations auxquelles l'utilisateur appartient (via memberships).
     *
     * @return BelongsToMany<Organisation, $this>
     */
    public function organisations(): BelongsToMany
    {
        return $this->belongsToMany(Organisation::class, 'memberships')
            ->withPivot(['role', 'actif'])
            ->withTimestamps();
    }

    /**
     * Sites créés par cet utilisateur.
     *
     * @return HasMany<Site, $this>
     */
    public function sitesCrees(): HasMany
    {
        return $this->hasMany(Site::class, 'cree_par');
    }

    /**
     * Droits d'accès de l'utilisateur sur des sites.
     *
     * @return HasMany<SiteAcces, $this>
     */
    public function siteAcces(): HasMany
    {
        return $this->hasMany(SiteAcces::class);
    }

    /**
     * Sites auxquels l'utilisateur a accès (via site_acces).
     *
     * @return BelongsToMany<Site, $this>
     */
    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class, 'site_acces')
            ->withPivot('niveau')
            ->withTimestamps();
    }

    /**
     * Commandes passées par cet utilisateur en tant que foyer.
     *
     * @return HasMany<Commande, $this>
     */
    public function commandesDemandees(): HasMany
    {
        return $this->hasMany(Commande::class, 'demandeur_user_id');
    }

    /**
     * Livraisons affectées à cet utilisateur en tant que livreur.
     *
     * @return HasMany<Livraison, $this>
     */
    public function livraisons(): HasMany
    {
        return $this->hasMany(Livraison::class, 'livreur_user_id');
    }

    /**
     * Tournées affectées à cet utilisateur en tant que livreur.
     *
     * @return HasMany<Tournee, $this>
     */
    public function tournees(): HasMany
    {
        return $this->hasMany(Tournee::class, 'livreur_user_id');
    }

    /**
     * Sites pour lesquels cet utilisateur est désigné livreur habituel.
     *
     * @return HasMany<LivreurHabituel, $this>
     */
    public function livreurHabituelPour(): HasMany
    {
        return $this->hasMany(LivreurHabituel::class, 'livreur_user_id');
    }
}

<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\NiveauAcces;
use App\Enums\RoleMembership;
use App\Traits\HasUuid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
            'canaux_alerte' => 'array',
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

    /**
     * Livreur habituel par défaut de ce foyer (préférence de compte,
     * contrat API `PATCH /api/me/reglages-alertes`).
     *
     * @return BelongsTo<User, $this>
     */
    public function livreurHabituelPrefere(): BelongsTo
    {
        return $this->belongsTo(User::class, 'livreur_habituel_user_id');
    }

    // === Cloisonnement (doc 07, §10) ===================================
    // Ces helpers matérialisent les frontières d'accès. Les policies s'appuient
    // dessus ; ils ne remplacent pas les contraintes de données (FK, index),
    // ils les complètent côté application.

    /**
     * L'utilisateur est-il membre actif de l'organisation donnée ?
     * Optionnellement, avec un rôle précis.
     */
    public function estMembreDe(Organisation $organisation, ?RoleMembership $role = null): bool
    {
        $query = $this->memberships()
            ->where('actif', true)
            ->where('organisation_id', $organisation->id);

        if ($role !== null) {
            $query->where('role', $role->value);
        }

        return $query->exists();
    }

    /**
     * L'utilisateur peut-il voir cette organisation ? Vrai s'il en est membre,
     * ou membre d'un de ses ancêtres : l'accès descend la hiérarchie
     * (un mandataire voit ses dépôts, un distributeur voit ses mandataires et
     * leurs dépôts), jamais l'inverse.
     */
    public function peutVoirOrganisation(Organisation $organisation): bool
    {
        // Identifiants de l'organisation cible et de tous ses ancêtres.
        $ids = [];
        $courant = $organisation;
        // Profondeur volontairement bornée (dépôt → mandataire → distributeur).
        while ($courant !== null && ! in_array($courant->id, $ids, true)) {
            $ids[] = $courant->id;
            $courant = $courant->parent;
        }

        return $this->memberships()
            ->where('actif', true)
            ->whereIn('organisation_id', $ids)
            ->exists();
    }

    /**
     * L'utilisateur peut-il administrer directement cette organisation ?
     * Membre direct (pas via la hiérarchie) avec un rôle non-livreur.
     */
    public function peutGererOrganisation(Organisation $organisation): bool
    {
        return $this->memberships()
            ->where('actif', true)
            ->where('organisation_id', $organisation->id)
            ->where('role', '!=', RoleMembership::Livreur->value)
            ->exists();
    }

    /**
     * L'utilisateur a-t-il un accès (quel qu'en soit le niveau) à ce site ?
     */
    public function aAccesAuSite(Site $site): bool
    {
        return $this->siteAcces()
            ->where('site_id', $site->id)
            ->exists();
    }

    /**
     * L'utilisateur peut-il gérer ce site (propriétaire ou gestionnaire) ?
     * Un simple observateur ne peut pas modifier.
     */
    public function peutGererSite(Site $site): bool
    {
        return $this->siteAcces()
            ->where('site_id', $site->id)
            ->whereIn('niveau', [
                NiveauAcces::Proprietaire->value,
                NiveauAcces::Gestionnaire->value,
            ])
            ->exists();
    }

    /**
     * L'utilisateur est-il propriétaire de ce site ? Réservé au partage
     * d'accès (contrat API, `POST /api/sites/{uuid}/partages`) : un
     * gestionnaire peut administrer le site mais pas en partager l'accès.
     */
    public function estProprietaireDuSite(Site $site): bool
    {
        return $this->siteAcces()
            ->where('site_id', $site->id)
            ->where('niveau', NiveauAcces::Proprietaire->value)
            ->exists();
    }
}

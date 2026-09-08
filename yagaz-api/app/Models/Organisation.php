<?php

namespace App\Models;

use App\Enums\TypeOrganisation;
use App\Traits\HasUuid;
use Database\Factories\OrganisationFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un acteur professionnel : dépôt, mandataire ou distributeur. Frontière de
 * cloisonnement (tenant) — doc 07, §2 `organisations`.
 */
#[Guarded([])]
class Organisation extends Model
{
    /** @use HasFactory<OrganisationFactory> */
    use HasFactory, HasUuid;

    protected function casts(): array
    {
        return [
            'type' => TypeOrganisation::class,
            'abonnement_actif' => 'boolean',
        ];
    }

    /**
     * Organisation parente dans la hiérarchie (dépôt → mandataire → distributeur).
     *
     * @return BelongsTo<Organisation, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'parent_id');
    }

    /**
     * Organisations filles (ex. dépôts rattachés à un mandataire).
     *
     * @return HasMany<Organisation, $this>
     */
    public function enfants(): HasMany
    {
        return $this->hasMany(Organisation::class, 'parent_id');
    }

    /**
     * Rattachements d'utilisateurs à cette organisation.
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Utilisateurs membres de cette organisation.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'memberships')
            ->withPivot(['role', 'actif'])
            ->withTimestamps();
    }

    /**
     * État de stock de l'organisation, par format.
     *
     * @return HasMany<Stock, $this>
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    /**
     * Commandes reçues par cette organisation (dépôt ou mandataire cible).
     *
     * @return HasMany<Commande, $this>
     */
    public function commandesRecues(): HasMany
    {
        return $this->hasMany(Commande::class, 'cible_org_id');
    }

    /**
     * Commandes émises par cette organisation (dépôt vers son mandataire).
     *
     * @return HasMany<Commande, $this>
     */
    public function commandesEmises(): HasMany
    {
        return $this->hasMany(Commande::class, 'demandeur_org_id');
    }

    /**
     * Tournées organisées par cette organisation.
     *
     * @return HasMany<Tournee, $this>
     */
    public function tournees(): HasMany
    {
        return $this->hasMany(Tournee::class);
    }

    /**
     * Alertes de tension de stock de cette organisation.
     *
     * @return HasMany<Alerte, $this>
     */
    public function alertes(): HasMany
    {
        return $this->hasMany(Alerte::class);
    }
}

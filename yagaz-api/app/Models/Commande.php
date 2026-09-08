<?php

namespace App\Models;

use App\Enums\ModePaiement;
use App\Enums\OrigineCommande;
use App\Enums\StatutCommande;
use App\Enums\StatutPaiement;
use App\Traits\HasUuid;
use Database\Factories\CommandeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Une demande de recharge, émise par un foyer ou un dépôt vers son
 * mandataire — doc 07, §6 `commandes`.
 *
 * `$fillable` explicite (audit sécurité Phase 4, [INFO] `$fillable`
 * explicite, ADR 0005 pt 3) : la commande n'est de toute façon jamais créée
 * que par `CycleCommande` via `forceFill`, mais les colonnes d'autorisation
 * (`cible_org_id`, `demandeur_user_id`, `demandeur_org_id`, `statut`,
 * `statut_paiement`, `commission_g`) restent volontairement hors fillable en
 * défense en profondeur, au cas où un futur appel les mass-assignerait par
 * erreur depuis une requête.
 */
#[Fillable(['origine', 'site_id', 'format_id', 'quantite', 'mode_paiement'])]
class Commande extends Model
{
    /** @use HasFactory<CommandeFactory> */
    use HasFactory, HasUuid;

    protected function casts(): array
    {
        return [
            'origine' => OrigineCommande::class,
            'statut' => StatutCommande::class,
            'mode_paiement' => ModePaiement::class,
            'statut_paiement' => StatutPaiement::class,
        ];
    }

    /**
     * Foyer demandeur (si origine = foyer).
     *
     * @return BelongsTo<User, $this>
     */
    public function demandeurUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'demandeur_user_id');
    }

    /**
     * Dépôt demandeur (si origine = depot).
     *
     * @return BelongsTo<Organisation, $this>
     */
    public function demandeurOrg(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'demandeur_org_id');
    }

    /**
     * Organisation cible : dépôt (commande foyer) ou mandataire (commande dépôt).
     *
     * @return BelongsTo<Organisation, $this>
     */
    public function cibleOrg(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'cible_org_id');
    }

    /**
     * Site de livraison (commande foyer).
     *
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return BelongsTo<FormatBouteille, $this>
     */
    public function format(): BelongsTo
    {
        return $this->belongsTo(FormatBouteille::class, 'format_id');
    }

    /**
     * @return HasMany<Livraison, $this>
     */
    public function livraisons(): HasMany
    {
        return $this->hasMany(Livraison::class);
    }

    /**
     * Livraison la plus récente (une commande n'en a normalement qu'une),
     * pour l'affichage du suivi (contrat API doc 10, §3, `GET /commandes/{uuid}`).
     *
     * @return HasOne<Livraison, $this>
     */
    public function livraison(): HasOne
    {
        return $this->hasOne(Livraison::class)->latestOfMany();
    }
}

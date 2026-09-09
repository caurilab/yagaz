<?php

namespace App\Models;

use App\Enums\RoleBouteille;
use App\Enums\TareSource;
use App\Traits\HasUuid;
use Database\Factories\BouteilleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Une bouteille suivie par un foyer, posée (ou non) sur un plateau — doc 07,
 * §4 `bouteilles`.
 *
 * `$fillable` explicite (audit sécurité, [INFO] `$fillable` explicite) :
 * seuls les champs saisissables par le client via l'API. `site_id` en est
 * volontairement exclu — dérivé côté serveur du site de la route (ADR 0005),
 * posé par affectation directe de propriété dans `BouteilleController`.
 */
#[Fillable(['format_id', 'tare_g', 'tare_source', 'tare_fiable', 'role_bouteille', 'seuil_bas_pct', 'plateau_id', 'pieces_manquantes'])]
class Bouteille extends Model
{
    /** @use HasFactory<BouteilleFactory> */
    use HasFactory, HasUuid;

    protected function casts(): array
    {
        return [
            'tare_source' => TareSource::class,
            'tare_fiable' => 'boolean',
            'role_bouteille' => RoleBouteille::class,
            'pieces_manquantes' => 'array',
        ];
    }

    /**
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
     * Plateau sur lequel la bouteille est actuellement posée (null si non posée).
     *
     * @return BelongsTo<Plateau, $this>
     */
    public function plateau(): BelongsTo
    {
        return $this->belongsTo(Plateau::class);
    }

    /**
     * @return HasMany<Mesure, $this>
     */
    public function mesures(): HasMany
    {
        return $this->hasMany(Mesure::class);
    }

    /**
     * Dernier état de niveau connu (cache).
     *
     * @return HasOne<NiveauCourant, $this>
     */
    public function niveauCourant(): HasOne
    {
        return $this->hasOne(NiveauCourant::class);
    }

    /**
     * @return HasMany<Alerte, $this>
     */
    public function alertes(): HasMany
    {
        return $this->hasMany(Alerte::class);
    }
}

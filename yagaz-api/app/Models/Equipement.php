<?php

namespace App\Models;

use App\Enums\StatutEquipement;
use App\Enums\TypeEquipement;
use App\Traits\HasUuid;
use Database\Factories\EquipementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un équipement du registre unifié `equipements` (ADR 0012) : balance,
 * capteur de température ou écran de cuisine.
 *
 * `$fillable` explicite (audit sécurité, [INFO] `$fillable` explicite) :
 * seuls `type` et `reference` sont saisissables par le client via l'API.
 * `site_id`, `statut` et `cree_par` sont dérivés côté serveur (ADR 0005),
 * posés via `forceFill`/`forceCreate` dans `EquipementController`.
 */
#[Fillable(['type', 'reference'])]
class Equipement extends Model
{
    /** @use HasFactory<EquipementFactory> */
    use HasFactory, HasUuid;

    protected function casts(): array
    {
        return [
            'type' => TypeEquipement::class,
            'statut' => StatutEquipement::class,
            'dernier_vu_at' => 'datetime',
        ];
    }

    /**
     * Site auquel l'équipement est affecté (null tant que non affecté).
     *
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Créateur de l'équipement — sert de fondement d'accès tant qu'il n'est
     * affecté à aucun site (`EquipementPolicy`).
     *
     * @return BelongsTo<User, $this>
     */
    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }
}

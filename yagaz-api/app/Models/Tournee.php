<?php

namespace App\Models;

use App\Enums\StatutTournee;
use App\Traits\HasUuid;
use Database\Factories\TourneeFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Regroupe des livraisons d'un mandataire (ou dépôt) pour une journée —
 * doc 07, §6 `tournees`. `uuid` ajouté en Phase 5 (contrat API doc 11, §1)
 * pour exposer l'identifiant sans révéler l'`id` interne.
 */
#[Guarded([])]
class Tournee extends Model
{
    /** @use HasFactory<TourneeFactory> */
    use HasFactory, HasUuid;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'statut' => StatutTournee::class,
        ];
    }

    /**
     * @return BelongsTo<Organisation, $this>
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function livreur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'livreur_user_id');
    }

    /**
     * @return HasMany<Livraison, $this>
     */
    public function livraisons(): HasMany
    {
        return $this->hasMany(Livraison::class);
    }

    /**
     * Lignes de la tournée : par dépôt et par format, pleines à déposer et
     * vides à récupérer (Phase 5, doc 11 §1).
     *
     * @return HasMany<TourneeLigne, $this>
     */
    public function lignes(): HasMany
    {
        return $this->hasMany(TourneeLigne::class);
    }
}

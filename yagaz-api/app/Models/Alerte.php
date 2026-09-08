<?php

namespace App\Models;

use App\Enums\CanalAlerte;
use App\Enums\StatutAlerte;
use App\Enums\TypeAlerte;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trace des franchissements de seuil et propositions, pour éviter le
 * re-spam — doc 07, §8 `alertes`.
 */
#[Guarded([])]
class Alerte extends Model
{
    /**
     * Uniquement `created_at` (pas d'`updated_at` au doc 07).
     */
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => TypeAlerte::class,
            'statut' => StatutAlerte::class,
            'canal' => CanalAlerte::class,
        ];
    }

    /**
     * @return BelongsTo<Bouteille, $this>
     */
    public function bouteille(): BelongsTo
    {
        return $this->belongsTo(Bouteille::class);
    }

    /**
     * Organisation concernée par une alerte de tension de stock.
     *
     * @return BelongsTo<Organisation, $this>
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * Commande concernée (Phase 5, doc 11 §3 : changement de statut,
     * proposition de livraison).
     *
     * @return BelongsTo<Commande, $this>
     */
    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class);
    }

    /**
     * Site du foyer destinataire (Phase 5, doc 11 §3).
     *
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Utilisateur destinataire de la notification (Phase 5, doc 11 §3,
     * `GET /api/notifications`). Distinct de la cible (`bouteille`/
     * `organisation`) : une alerte de tension de stock n'a pas forcément de
     * destinataire précis, une notification en a toujours un.
     *
     * @return BelongsTo<User, $this>
     */
    public function destinataireUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destinataire_user_id');
    }
}

<?php

namespace App\Models;

use App\Enums\RoleMembership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rattache un `user` à une `organisation` avec un rôle — doc 07, §2 `memberships`.
 *
 * Table d'autorisation : aucune colonne n'est mass-assignable (le rôle et
 * l'organisation ne doivent jamais provenir d'une requête utilisateur sans
 * passer par `forceCreate`/`forceFill` explicite et contrôlé).
 */
class Membership extends Model
{
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'role' => RoleMembership::class,
            'actif' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Organisation, $this>
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}

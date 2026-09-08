<?php

namespace App\Models;

use App\Enums\RoleMembership;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rattache un `user` à une `organisation` avec un rôle — doc 07, §2 `memberships`.
 */
#[Guarded([])]
class Membership extends Model
{
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

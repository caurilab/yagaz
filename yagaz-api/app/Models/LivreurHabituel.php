<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Le foyer peut désigner un livreur prévenu automatiquement au seuil bas —
 * doc 07, §8 `livreur_habituel`.
 */
#[Guarded([])]
class LivreurHabituel extends Model
{
    protected $table = 'livreur_habituel';

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
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
     * @return BelongsTo<User, $this>
     */
    public function livreur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'livreur_user_id');
    }
}

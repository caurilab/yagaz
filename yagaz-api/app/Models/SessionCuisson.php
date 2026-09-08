<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une session de cuisson détectée pour un site (ADR 0011) : ouverte quand la
 * température de cuisine dépasse `seuil_cuisson_c`, fermée quand elle
 * redescend. `fin_at` reste `null` tant que la session est en cours.
 */
#[Guarded([])]
class SessionCuisson extends Model
{
    protected $table = 'sessions_cuisson';

    protected function casts(): array
    {
        return [
            'debut_at' => 'datetime',
            'fin_at' => 'datetime',
            'temp_max_c' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}

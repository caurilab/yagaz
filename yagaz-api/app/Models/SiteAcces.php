<?php

namespace App\Models;

use App\Enums\NiveauAcces;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Qui peut voir/gérer quel site, et avec quel niveau — cœur du multi-sites et
 * du cloisonnement entre foyers (doc 07, §3 `site_acces`).
 *
 * Table d'autorisation : aucune colonne n'est mass-assignable (le niveau
 * d'accès ne doit jamais provenir d'une requête utilisateur sans passer par
 * `forceCreate`/`forceFill` explicite et contrôlé).
 */
class SiteAcces extends Model
{
    protected $table = 'site_acces';

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'niveau' => NiveauAcces::class,
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Résout le périmètre de sites du foyer pour les endpoints transverses au
 * foyer (historique, analyse — doc 13, §1/§2/§6) : par défaut tous les sites
 * auxquels l'utilisateur a un `site_acces`, ou un seul site précis si
 * `site_uuid` est fourni. `404` si ce site n'existe pas ou est hors
 * périmètre (on ne révèle pas son existence à un tiers — même logique que
 * `AutoriseCloisonnement`).
 */
trait ResoutPerimetreFoyer
{
    /**
     * @return Collection<int, int> Identifiants internes des sites du périmètre.
     */
    private function perimetreSiteIds(User $user, ?string $siteUuid): Collection
    {
        if ($siteUuid === null) {
            return $user->sites()->pluck('sites.id');
        }

        $site = Site::where('uuid', $siteUuid)->first();

        abort_if($site === null, 404);
        abort_unless($user->aAccesAuSite($site), 404);

        return collect([$site->id]);
    }
}

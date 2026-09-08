<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Bouteille;
use App\Models\Site;
use App\Models\User;

/**
 * Distingue, pour les écritures sur un site/une bouteille, les deux causes
 * de refus du contrat API (§« Conventions générales ») : `404` quand
 * l'utilisateur n'a aucun accès (on ne révèle pas l'existence de la
 * ressource), `403` quand il a accès mais pas le niveau requis (ex. un
 * observateur qui tente de gérer).
 */
trait AutoriseCloisonnement
{
    private function autoriserSite(User $user, Site $site, string $ability): void
    {
        abort_unless($user->aAccesAuSite($site), 404);
        abort_unless($user->can($ability, $site), 403);
    }

    private function autoriserBouteille(User $user, Bouteille $bouteille, string $ability): void
    {
        abort_unless($user->aAccesAuSite($bouteille->site), 404);
        abort_unless($user->can($ability, $bouteille), 403);
    }
}

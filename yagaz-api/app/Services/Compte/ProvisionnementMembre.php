<?php

namespace App\Services\Compte;

use App\Enums\RoleMembership;
use App\Models\Membership;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Provisioning « descendant » d'un membre (contrat API doc 11 — création de
 * comptes par la hiérarchie) : un niveau crée/rattache le niveau du dessous par
 * numéro de téléphone. Un mandataire crée le gérant d'un dépôt, un dépôt ajoute
 * ses livreurs, la commande d'amorçage Yagaz crée un mandataire/distributeur.
 *
 * Idempotent sur le membership (contrainte unique user+organisation+role) : ré-
 * attacher réactive un membership désactivé plutôt que d'échouer.
 *
 * Sécurité : `Membership` n'a aucune colonne mass-assignable (table
 * d'autorisation) ; on pose donc les valeurs par `forceCreate`/`forceFill`
 * explicites, jamais depuis une entrée brute.
 */
class ProvisionnementMembre
{
    /**
     * Crée l'utilisateur s'il n'existe pas (par téléphone), puis lui attache le
     * rôle sur l'organisation.
     *
     * @param  string|null  $motDePasse  mot de passe imposé par l'invitant ; si
     *                                   null et que l'utilisateur est créé, un
     *                                   mot de passe temporaire est généré et
     *                                   renvoyé dans `mot_de_passe_temporaire`
     *                                   (à communiquer à la personne).
     * @return array{user: User, cree: bool, mot_de_passe_temporaire: string|null}
     */
    public function attacher(
        string $nom,
        string $telephone,
        RoleMembership $role,
        Organisation $organisation,
        ?string $motDePasse = null
    ): array {
        return DB::transaction(function () use ($nom, $telephone, $role, $organisation, $motDePasse): array {
            $user = User::where('telephone', $telephone)->first();
            $cree = false;
            $motDePasseTemporaire = null;

            if ($user === null) {
                $motDePasseClair = $motDePasse ?? Str::password(12, symbols: false);
                $user = User::create([
                    'name' => $nom,
                    'telephone' => $telephone,
                    'password' => $motDePasseClair,
                    'langue' => 'fr',
                ]);
                $cree = true;
                // On ne renvoie le mot de passe que si on l'a généré nous-mêmes.
                $motDePasseTemporaire = $motDePasse === null ? $motDePasseClair : null;
            }

            $membership = Membership::query()
                ->where('user_id', $user->id)
                ->where('organisation_id', $organisation->id)
                ->where('role', $role->value)
                ->first();

            if ($membership === null) {
                Membership::query()->forceCreate([
                    'user_id' => $user->id,
                    'organisation_id' => $organisation->id,
                    'role' => $role,
                    'actif' => true,
                ]);
            } elseif (! $membership->actif) {
                $membership->forceFill(['actif' => true])->save();
            }

            return [
                'user' => $user,
                'cree' => $cree,
                'mot_de_passe_temporaire' => $motDePasseTemporaire,
            ];
        });
    }
}

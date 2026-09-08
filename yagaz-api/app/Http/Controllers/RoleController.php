<?php

namespace App\Http\Controllers;

use App\Enums\RoleMembership;
use App\Enums\TypeOrganisation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Rôles et espaces disponibles pour le compte courant (contrat API doc 10,
 * §1, `GET /api/mes-roles`) : foyer, dépôt(s) géré(s), livreur. L'app s'en
 * sert pour proposer les espaces disponibles.
 */
class RoleController extends Controller
{
    public function mesRoles(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'foyer' => $user->sites()->exists(),
            'depots' => $this->organisationsParRole($user, TypeOrganisation::Depot, RoleMembership::GerantDepot),
            'mandataires' => $this->organisationsParRole($user, TypeOrganisation::Mandataire, RoleMembership::Mandataire),
            'distributeurs' => $this->organisationsParRole($user, TypeOrganisation::Distributeur, RoleMembership::Distributeur),
            'livreur' => $user->memberships()
                ->where('actif', true)
                ->where('role', RoleMembership::Livreur->value)
                ->exists(),
        ]);
    }

    /**
     * Organisations d'un type donné où l'utilisateur a un rôle actif
     * (projection minimale uuid + nom, pour le sélecteur d'espace côté client).
     *
     * @return Collection<int, array{uuid: string, nom: string}>
     */
    private function organisationsParRole(User $user, TypeOrganisation $type, RoleMembership $role): Collection
    {
        return $user->organisations()
            ->where('organisations.type', $type->value)
            ->wherePivot('actif', true)
            ->wherePivot('role', $role->value)
            ->get(['organisations.uuid', 'organisations.nom'])
            ->map(fn ($org) => ['uuid' => $org->uuid, 'nom' => $org->nom])
            ->values();
    }
}

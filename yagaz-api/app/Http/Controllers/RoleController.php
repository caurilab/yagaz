<?php

namespace App\Http\Controllers;

use App\Enums\RoleMembership;
use App\Enums\TypeOrganisation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $depots = $user->organisations()
            ->where('organisations.type', TypeOrganisation::Depot->value)
            ->wherePivot('actif', true)
            ->wherePivot('role', RoleMembership::GerantDepot->value)
            ->get(['organisations.uuid', 'organisations.nom']);

        return response()->json([
            'foyer' => $user->sites()->exists(),
            'depots' => $depots->map(fn ($depot) => [
                'uuid' => $depot->uuid,
                'nom' => $depot->nom,
            ])->values(),
            'livreur' => $user->memberships()
                ->where('actif', true)
                ->where('role', RoleMembership::Livreur->value)
                ->exists(),
        ]);
    }
}

<?php

namespace App\Http\Resources;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation publique d'un utilisateur (contrat API,
 * §« Conventions générales » : `uuid` exposé, jamais `password`/
 * `remember_token`).
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'nom' => $this->name,
            'telephone' => $this->telephone,
            'email' => $this->email,
            'langue' => $this->langue,
            'roles' => $this->memberships()
                ->where('actif', true)
                ->with('organisation:id,uuid,nom,type')
                ->get()
                ->filter(fn (Membership $membership): bool => $membership->organisation !== null)
                ->map(fn (Membership $membership): array => [
                    'role' => $membership->role->value,
                    'organisation' => [
                        'uuid' => $membership->organisation->uuid,
                        'nom' => $membership->organisation->nom,
                        'type' => $membership->organisation->type->value,
                    ],
                ])
                ->values()
                ->all(),
            'reglages_alertes' => [
                'canaux' => $this->canaux_alerte ?? [],
                'livreur_habituel' => $this->livreur_habituel_user_id !== null
                    ? User::whereKey($this->livreur_habituel_user_id)->value('telephone')
                    : null,
            ],
        ];
    }
}

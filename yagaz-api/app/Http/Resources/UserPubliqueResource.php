<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Projection minimale d'un utilisateur, pour un tiers (audit sécurité,
 * [FAIBLE] fuite de PII dans la réponse de partage) : ni `email`, ni
 * `telephone`, ni `reglages_alertes` — réservés à `/api/me` (l'utilisateur
 * lui-même, via `UserResource`).
 *
 * @mixin User
 */
class UserPubliqueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'nom' => $this->name,
        ];
    }
}

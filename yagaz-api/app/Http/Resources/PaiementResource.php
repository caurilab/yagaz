<?php

namespace App\Http\Resources;

use App\Models\Paiement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Statut d'un paiement Mobile Money (ADR 0010, v2 brique 1 ; contrat API
 * `POST`/`GET /api/commandes/{uuid}/paiement`). Ne renvoie jamais le secret
 * webhook ni de donnée brute de signature.
 *
 * @mixin Paiement
 */
class PaiementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'provider' => $this->provider,
            'reference' => $this->reference,
            'montant' => $this->montant,
            'devise' => $this->devise,
            'statut' => $this->statut->value,
            'created_at' => $this->created_at,
        ];
    }
}

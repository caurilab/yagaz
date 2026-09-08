<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Le dépôt propose une livraison à un foyer (contrat API doc 10,
 * `POST /api/depots/{orgUuid}/propositions`).
 */
class DepotPropositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'site_uuid' => ['required', 'uuid', 'exists:sites,uuid'],
            'format_id' => ['required', 'integer', 'exists:formats_bouteille,id'],
            'quantite' => ['required', 'integer', 'min:1'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Le livreur habituel propose une livraison pour un de ses foyers habituels
 * en tension (ADR 0009, maillon C, `POST /api/livreur/propositions`).
 * `depot_uuid` n'est requis que si le livreur est membre de plusieurs dépôts
 * (sinon le dépôt unique est pris automatiquement — vérifié dans le
 * contrôleur). Le format n'est pas saisi : dérivé de la bouteille en
 * tension du site, jamais laissé au client (ADR 0005).
 */
class LivreurPropositionRequest extends FormRequest
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
            'site_uuid' => ['required', 'uuid'],
            'depot_uuid' => ['sometimes', 'nullable', 'uuid'],
            'quantite' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}

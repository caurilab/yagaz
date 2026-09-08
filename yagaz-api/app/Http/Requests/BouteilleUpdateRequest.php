<?php

namespace App\Http\Requests;

use App\Enums\RoleBouteille;
use App\Enums\TareSource;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'une bouteille (contrat API, `PATCH /api/bouteilles/{uuid}`).
 * Passer `role_bouteille=active` permute avec l'ancienne active du site
 * (géré dans le contrôleur, en transaction).
 */
class BouteilleUpdateRequest extends FormRequest
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
            'role_bouteille' => ['sometimes', Rule::enum(RoleBouteille::class)],
            'seuil_bas_pct' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'tare_g' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'tare_source' => ['sometimes', Rule::enum(TareSource::class)],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\StatutEquipement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'un équipement (contrat API, `PATCH /api/equipements/{uuid}`
 * — ADR 0012) : affectation à un site (`site_uuid`, `null` pour retirer
 * l'affectation) et/ou changement de statut. L'accès au site désigné est
 * vérifié dans le contrôleur.
 */
class EquipementUpdateRequest extends FormRequest
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
            'site_uuid' => ['sometimes', 'nullable', 'string', 'uuid', 'exists:sites,uuid'],
            'statut' => ['sometimes', Rule::enum(StatutEquipement::class)],
        ];
    }
}

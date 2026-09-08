<?php

namespace App\Http\Requests;

use App\Enums\TypeEquipement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Enregistrement d'un équipement (contrat API, `POST /api/equipements` —
 * ADR 0012). `site_id`, `statut` et `cree_par` sont dérivés côté serveur
 * (ADR 0005), jamais reçus du client. L'accès au site désigné par
 * `site_uuid` (le cas échéant) est vérifié dans le contrôleur.
 */
class EquipementStoreRequest extends FormRequest
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
            'type' => ['required', Rule::enum(TypeEquipement::class)],
            'reference' => ['required', 'string', 'max:255', 'unique:equipements,reference'],
            'site_uuid' => ['nullable', 'string', 'uuid', 'exists:sites,uuid'],
        ];
    }
}

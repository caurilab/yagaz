<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Changement de statut d'une alerte (contrat API,
 * `PATCH /api/alertes/{id}`) : uniquement `vue` ou `resolue` (`emise` est
 * l'état initial, jamais reçu du client).
 */
class AlerteUpdateRequest extends FormRequest
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
            'statut' => ['required', Rule::in(['vue', 'resolue'])],
        ];
    }
}

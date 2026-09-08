<?php

namespace App\Http\Requests;

use App\Enums\StatutAlerte;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtre de la liste des alertes (contrat API,
 * `GET /api/alertes?statut=emise`).
 */
class AlerteIndexRequest extends FormRequest
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
            'statut' => ['sometimes', Rule::enum(StatutAlerte::class)],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Paramètres de l'analyse température/cuisson d'un site (ADR 0011,
 * `GET /api/sites/{uuid}/temperature/analyse?periode=jour|semaine|mois`).
 */
class TemperatureAnalyseRequest extends FormRequest
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
            'periode' => ['sometimes', Rule::in(['jour', 'semaine', 'mois'])],
        ];
    }
}

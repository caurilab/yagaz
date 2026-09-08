<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Fenêtre temporelle des agrégats distributeur (contrat API doc 11,
 * `GET /api/distributeurs/{orgUuid}/demande` et `.../volumes`).
 */
class DistributeurPeriodeRequest extends FormRequest
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
            'depuis' => ['required', 'date'],
            'jusqua' => ['required', 'date', 'after_or_equal:depuis'],
            'pas' => ['sometimes', Rule::in(['jour', 'semaine', 'mois'])],
        ];
    }
}

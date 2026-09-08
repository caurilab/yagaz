<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Paramètres du tableau d'analyses foyer (doc 13, §2,
 * `GET /api/analyse?site_uuid?&periode=mois|semaine|annee`).
 */
class AnalyseIndexRequest extends FormRequest
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
            'site_uuid' => ['sometimes', 'uuid'],
            'periode' => ['sometimes', Rule::in(['mois', 'semaine', 'annee'])],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\StatutLivraison;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtre des missions du livreur (contrat API doc 10,
 * `GET /api/livreur/missions?statut=`).
 */
class LivreurMissionsIndexRequest extends FormRequest
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
            'statut' => ['sometimes', Rule::enum(StatutLivraison::class)],
        ];
    }
}

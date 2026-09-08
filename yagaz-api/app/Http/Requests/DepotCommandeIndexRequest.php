<?php

namespace App\Http\Requests;

use App\Enums\StatutCommande;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtre de la file de commandes entrantes d'un dépôt (contrat API doc 10,
 * `GET /api/depots/{orgUuid}/commandes?statut=`).
 */
class DepotCommandeIndexRequest extends FormRequest
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
            'statut' => ['sometimes', Rule::enum(StatutCommande::class)],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtre de l'historique unifié (doc 13, §1,
 * `GET /api/historique?site_uuid?&depuis?&type?&page?&par_page?`).
 */
class HistoriqueIndexRequest extends FormRequest
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
            'depuis' => ['sometimes', 'date'],
            // Filtre libre sur le `type` (ex. « commande_livree ») ou la
            // catégorie/`icone` (ex. « commande ») — voir
            // `AgregationHistorique::evenements()`.
            'type' => ['sometimes', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'par_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Ajustement manuel du stock d'un dépôt (contrat API doc 10,
 * `PATCH /api/depots/{orgUuid}/stocks/{format_id}`). Chaque variation est
 * journalisée dans `mouvements_stock` (type `ajustement`) par le contrôleur.
 */
class StockUpdateRequest extends FormRequest
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
            'pleines' => ['sometimes', 'integer', 'min:0'],
            'vides' => ['sometimes', 'integer', 'min:0'],
            'seuil_plein_bas' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->hasAny(['pleines', 'vides', 'seuil_plein_bas'])) {
                $validator->errors()->add('pleines', 'Au moins un champ doit être renseigné.');
            }
        });
    }
}

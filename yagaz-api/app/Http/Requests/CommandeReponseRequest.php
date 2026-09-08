<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Réponse du foyer à une proposition (contrat API doc 10,
 * `POST /api/commandes/{uuid}/reponse`).
 */
class CommandeReponseRequest extends FormRequest
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
            'accepte' => ['required', 'boolean'],
        ];
    }
}

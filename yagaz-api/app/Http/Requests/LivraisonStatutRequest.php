<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Le livreur fait avancer le statut de sa mission (contrat API doc 10,
 * `PATCH /api/livraisons/{id}/statut`). `affectee` n'est jamais reçu du
 * client : c'est l'état initial, posé par le dépôt à la création.
 */
class LivraisonStatutRequest extends FormRequest
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
            'statut' => ['required', Rule::in(['en_route', 'livree', 'vide_recupere'])],
            'vides_recuperes' => ['nullable', 'integer', 'min:0'],
        ];
    }
}

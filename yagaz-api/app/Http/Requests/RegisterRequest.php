<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Création d'un foyer (contrat API, `POST /api/auth/register`) : `telephone`
 * est l'identifiant principal, unique.
 */
class RegisterRequest extends FormRequest
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
            'nom' => ['required', 'string', 'max:255'],
            'telephone' => ['required', 'string', 'max:30', 'unique:users,telephone'],
            'mot_de_passe' => ['required', 'string', 'min:8'],
            'langue' => ['nullable', 'string', 'max:5'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormaliseTelephone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Connexion d'un foyer (contrat API, `POST /api/auth/login`).
 */
class LoginRequest extends FormRequest
{
    use NormaliseTelephone;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise `telephone` AVANT la validation : le login recherche
     * toujours l'utilisateur sur la forme normalisée, quelle que soit la
     * variante saisie (audit sécurité, [MOYEN] normalisation du téléphone).
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('telephone')) {
            $this->merge(['telephone' => $this->normaliserTelephone($this->input('telephone'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'telephone' => ['required', 'string', $this->regleFormatTelephone()],
            'mot_de_passe' => ['required', 'string'],
        ];
    }
}

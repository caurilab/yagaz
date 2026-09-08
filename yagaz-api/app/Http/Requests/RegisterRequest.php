<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormaliseTelephone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Création d'un foyer (contrat API, `POST /api/auth/register`) : `telephone`
 * est l'identifiant principal, unique.
 */
class RegisterRequest extends FormRequest
{
    use NormaliseTelephone;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise `telephone` AVANT la validation, pour que la contrainte
     * `unique` et la valeur stockée portent toutes deux sur la forme
     * normalisée (audit sécurité, [MOYEN] normalisation du téléphone).
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
            'nom' => ['required', 'string', 'max:255'],
            'telephone' => ['required', 'string', 'max:30', $this->regleFormatTelephone(), 'unique:users,telephone'],
            // bcrypt tronque silencieusement au-delà de 72 octets (audit
            // sécurité, [INFO] durcir le login) : `max:72` évite qu'un mot de
            // passe plus long soit accepté puis vérifié sur un préfixe.
            'mot_de_passe' => ['required', 'string', 'min:8', 'max:72'],
            'langue' => ['nullable', 'string', 'max:5'],
        ];
    }
}

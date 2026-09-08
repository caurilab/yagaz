<?php

namespace App\Http\Requests;

use App\Enums\NiveauAcces;
use App\Http\Requests\Concerns\NormaliseTelephone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Partage d'accès à un site (contrat API, `POST /api/sites/{uuid}/partages`) :
 * le bénéficiaire doit être un utilisateur déjà inscrit.
 */
class SitePartageRequest extends FormRequest
{
    use NormaliseTelephone;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise `telephone` AVANT la validation, pour que la recherche du
     * bénéficiaire (`exists:users,telephone`) porte sur la forme normalisée
     * (audit sécurité, [MOYEN] normalisation du téléphone).
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
            'telephone' => ['required', 'string', $this->regleFormatTelephone(), 'exists:users,telephone'],
            'niveau' => ['required', Rule::enum(NiveauAcces::class)],
        ];
    }
}

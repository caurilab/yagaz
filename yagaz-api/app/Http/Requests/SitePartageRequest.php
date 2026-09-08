<?php

namespace App\Http\Requests;

use App\Enums\NiveauAcces;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Partage d'accès à un site (contrat API, `POST /api/sites/{uuid}/partages`) :
 * le bénéficiaire doit être un utilisateur déjà inscrit.
 */
class SitePartageRequest extends FormRequest
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
            'telephone' => ['required', 'string', 'exists:users,telephone'],
            'niveau' => ['required', Rule::enum(NiveauAcces::class)],
        ];
    }
}

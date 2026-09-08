<?php

namespace App\Http\Requests;

use App\Enums\CanalAlerte;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Préférences d'alerte du foyer (contrat API,
 * `PATCH /api/me/reglages-alertes`). `livreur_habituel` (optionnel) est un
 * numéro de téléphone d'utilisateur déjà inscrit ; `null` retire la
 * préférence.
 */
class ReglagesAlertesRequest extends FormRequest
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
            'canaux' => ['required', 'array'],
            'canaux.*' => [Rule::enum(CanalAlerte::class)],
            'livreur_habituel' => ['sometimes', 'nullable', 'string', 'exists:users,telephone'],
        ];
    }
}

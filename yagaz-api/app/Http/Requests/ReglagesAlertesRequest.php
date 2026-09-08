<?php

namespace App\Http\Requests;

use App\Enums\CanalAlerte;
use App\Http\Requests\Concerns\NormaliseTelephone;
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
    use NormaliseTelephone;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise `livreur_habituel` AVANT la validation (quand renseigné),
     * pour que la recherche (`exists:users,telephone`) porte sur la forme
     * normalisée (audit sécurité, [MOYEN] normalisation du téléphone).
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('livreur_habituel')) {
            $this->merge(['livreur_habituel' => $this->normaliserTelephone($this->input('livreur_habituel'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'canaux' => ['required', 'array'],
            'canaux.*' => [Rule::enum(CanalAlerte::class)],
            'livreur_habituel' => ['sometimes', 'nullable', 'string', $this->regleFormatTelephone(), 'exists:users,telephone'],
        ];
    }
}

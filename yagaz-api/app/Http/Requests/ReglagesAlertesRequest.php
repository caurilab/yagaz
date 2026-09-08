<?php

namespace App\Http\Requests;

use App\Enums\CanalAlerte;
use App\Enums\RoleMembership;
use App\Http\Requests\Concerns\NormaliseTelephone;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
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

    /**
     * `livreur_habituel` ne peut désigner qu'un vrai livreur (audit
     * sécurité, [FAIBLE] cible restreinte réglages d'alerte) : `exists:users,
     * telephone` vérifie seulement qu'un compte existe, pas son rôle — sans
     * ce contrôle, un foyer pourrait lier n'importe quel compte (un autre
     * foyer, un gérant de dépôt, etc.) comme « livreur habituel ». Un
     * utilisateur est un livreur s'il a au moins un membership actif de rôle
     * `livreur` (doc 07, §2 `memberships`).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('livreur_habituel') || $validator->errors()->has('livreur_habituel')) {
                return;
            }

            $utilisateur = User::where('telephone', $this->input('livreur_habituel'))->first();

            $estLivreur = $utilisateur !== null && $utilisateur->memberships()
                ->where('actif', true)
                ->where('role', RoleMembership::Livreur->value)
                ->exists();

            if (! $estLivreur) {
                $validator->errors()->add('livreur_habituel', 'Ce numéro ne correspond pas à un livreur.');
            }
        });
    }
}

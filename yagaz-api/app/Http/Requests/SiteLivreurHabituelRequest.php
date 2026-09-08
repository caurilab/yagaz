<?php

namespace App\Http\Requests;

use App\Enums\RoleMembership;
use App\Http\Requests\Concerns\NormaliseTelephone;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Désignation du livreur habituel **du site** (ADR 0009, maillons A et C ;
 * `POST /api/sites/{uuid}/livreur-habituel`) : distinct de
 * `users.livreur_habituel_user_id` (préférence par défaut du compte, réglée
 * via `PATCH /api/me/reglages-alertes`), qui n'est lu par aucun maillon
 * automatique. Cette désignation-ci écrit `livreur_habituel` (site_id,
 * livreur_user_id), la table dont dépendent réellement A (notification) et
 * C (droit de proposer).
 */
class SiteLivreurHabituelRequest extends FormRequest
{
    use NormaliseTelephone;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise `telephone` AVANT la validation (même raison que
     * `SitePartageRequest`/`ReglagesAlertesRequest` : la recherche
     * `exists:users,telephone` doit porter sur la forme normalisée).
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
        ];
    }

    /**
     * La cible doit être un vrai livreur (au moins un `Membership` actif de
     * rôle `livreur`) — même restriction que `reglages-alertes` (audit
     * sécurité, [FAIBLE] cible restreinte réglages d'alerte) : sans ce
     * contrôle, un foyer pourrait désigner n'importe quel compte comme
     * « livreur habituel » du site.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('telephone') || $validator->errors()->has('telephone')) {
                return;
            }

            $utilisateur = User::where('telephone', $this->input('telephone'))->first();

            $estLivreur = $utilisateur !== null && $utilisateur->memberships()
                ->where('actif', true)
                ->where('role', RoleMembership::Livreur->value)
                ->exists();

            if (! $estLivreur) {
                $validator->errors()->add('telephone', 'Ce numéro ne correspond pas à un livreur.');
            }
        });
    }
}

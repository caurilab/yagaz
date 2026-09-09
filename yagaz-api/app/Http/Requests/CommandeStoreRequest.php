<?php

namespace App\Http\Requests;

use App\Enums\TypeCommande;
use App\Enums\TypeOrganisation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Un foyer commande une recharge (contrat API doc 10, `POST /api/commandes`).
 * `site_uuid`/`depot_uuid` sont résolus et contrôlés (accès au site,
 * existence du dépôt) dans le contrôleur — pas de colonne d'autorisation
 * reçue telle quelle (ADR 0005).
 */
class CommandeStoreRequest extends FormRequest
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
            'site_uuid' => ['required', 'uuid', 'exists:sites,uuid'],
            'format_id' => ['required', 'integer', 'exists:formats_bouteille,id'],
            'quantite' => ['required', 'integer', 'min:1', 'max:100'],
            'type' => ['sometimes', Rule::enum(TypeCommande::class)],
            'depot_uuid' => [
                'required',
                'uuid',
                Rule::exists('organisations', 'uuid')->where('type', TypeOrganisation::Depot->value),
            ],
        ];
    }
}

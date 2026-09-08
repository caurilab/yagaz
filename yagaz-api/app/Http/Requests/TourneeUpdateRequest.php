<?php

namespace App\Http\Requests;

use App\Enums\StatutTournee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Le mandataire ajuste/valide une tournée (contrat API doc 11,
 * `PATCH /api/tournees/{uuid}`) : tous les champs sont optionnels,
 * indépendamment ajustables. Fournir `lignes` remplace intégralement les
 * lignes existantes (`CycleTournee::remplacerLignes`).
 */
class TourneeUpdateRequest extends FormRequest
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
            'statut' => ['sometimes', Rule::enum(StatutTournee::class)],
            'livreur_user_id' => ['sometimes', 'nullable', 'uuid', 'exists:users,uuid'],
            'lignes' => ['sometimes', 'array', 'min:1'],
            'lignes.*.depot_uuid' => ['required_with:lignes', 'uuid'],
            'lignes.*.format_id' => ['required_with:lignes', 'integer', 'exists:formats_bouteille,id'],
            'lignes.*.pleines' => ['required_with:lignes', 'integer', 'min:0'],
            'lignes.*.vides_a_recuperer' => ['required_with:lignes', 'integer', 'min:0'],
        ];
    }
}

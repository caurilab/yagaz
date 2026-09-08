<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Le dépôt propose une livraison à un foyer (contrat API doc 10,
 * `POST /api/depots/{orgUuid}/propositions`).
 *
 * Pas de `exists:sites,uuid` ici (audit sécurité Phase 4, [MOYEN]) : ce
 * contrôle fuiterait l'existence d'un site hors du périmètre du dépôt via un
 * 422 distinct du 404 « site non trouvé ». La résolution du site — et son
 * rattachement au dépôt — est faite dans `DepotCommandeController::propositions`,
 * qui répond uniformément 404 dans les deux cas.
 */
class DepotPropositionRequest extends FormRequest
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
            'site_uuid' => ['required', 'uuid'],
            'format_id' => ['required', 'integer', 'exists:formats_bouteille,id'],
            'quantite' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Le mandataire crée/valide une tournée à partir des réappros (contrat API
 * doc 11, `POST /api/mandataires/{orgUuid}/tournees`). L'appartenance de
 * chaque dépôt au mandataire est vérifiée dans le service
 * (`CycleTournee::remplacerLignes`), pas ici (audit sécurité Phase 4,
 * [MOYEN] — même logique que `DepotPropositionRequest` : ne pas fuiter
 * l'existence d'un dépôt hors périmètre via un code d'erreur distinct).
 */
class MandataireTourneeStoreRequest extends FormRequest
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
            'date' => ['required', 'date'],
            'livreur_user_id' => ['nullable', 'uuid', 'exists:users,uuid'],
            'lignes' => ['required', 'array', 'min:1'],
            'lignes.*.depot_uuid' => ['required', 'uuid'],
            'lignes.*.format_id' => ['required', 'integer', 'exists:formats_bouteille,id'],
            'lignes.*.pleines' => ['required', 'integer', 'min:0'],
            'lignes.*.vides_a_recuperer' => ['required', 'integer', 'min:0'],
        ];
    }
}

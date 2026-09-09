<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Le mandataire crée un dépôt rattaché à son organisation (contrat API doc
 * 11, `POST /api/mandataires/{orgUuid}/depots`). L'appartenance au mandataire
 * (policy `gererMandataire`) est vérifiée dans le contrôleur ; ici, seule la
 * forme de la saisie est validée.
 */
class MandataireDepotStoreRequest extends FormRequest
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
            'nom' => ['required', 'string', 'max:120'],
            'zone' => ['nullable', 'string', 'max:120'],
            // Gérant du dépôt (optionnel) : provisioning descendant. S'il est
            // fourni, on crée/rattache son compte par téléphone. `gerant_nom`
            // et `gerant_telephone` vont de pair ; le mot de passe est
            // optionnel (généré et renvoyé si absent).
            'gerant_nom' => ['required_with:gerant_telephone', 'nullable', 'string', 'max:120'],
            'gerant_telephone' => ['required_with:gerant_nom', 'nullable', 'string', 'max:30'],
            'gerant_mot_de_passe' => ['nullable', 'string', 'min:8', 'max:100'],
        ];
    }
}

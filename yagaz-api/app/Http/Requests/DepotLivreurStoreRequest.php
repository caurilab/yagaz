<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Le gérant d'un dépôt ajoute un livreur à son équipe (provisioning descendant,
 * `POST /api/depots/{orgUuid}/livreurs`). L'appartenance au dépôt (policy
 * `gererDepot`) est vérifiée dans le contrôleur ; ici, seule la forme de la
 * saisie est validée. Le compte est créé/rattaché par téléphone ; le mot de
 * passe est optionnel (généré et renvoyé une fois si absent).
 */
class DepotLivreurStoreRequest extends FormRequest
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
            'telephone' => ['required', 'string', 'max:30'],
            'mot_de_passe' => ['nullable', 'string', 'min:8', 'max:100'],
        ];
    }
}

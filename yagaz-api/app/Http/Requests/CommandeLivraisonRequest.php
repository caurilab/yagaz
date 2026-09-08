<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Le dépôt affecte une livraison (contrat API doc 10,
 * `POST /api/commandes/{uuid}/livraison`). `livreur_user_id` porte, malgré
 * son nom (repris du contrat), l'`uuid` public du livreur — jamais l'id
 * interne (doc 09, §« Conventions générales »). L'appartenance au dépôt est
 * vérifiée dans le service (`CycleCommande::affecterLivreur`).
 */
class CommandeLivraisonRequest extends FormRequest
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
            'livreur_user_id' => ['nullable', 'uuid', 'exists:users,uuid'],
        ];
    }
}

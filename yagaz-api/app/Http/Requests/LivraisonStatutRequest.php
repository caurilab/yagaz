<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Le livreur fait avancer le statut de sa mission (contrat API doc 10,
 * `PATCH /api/livraisons/{id}/statut`). `affectee` n'est jamais reçu du
 * client : c'est l'état initial, posé par le dépôt à la création.
 */
class LivraisonStatutRequest extends FormRequest
{
    /**
     * Borne haute large, seulement pour rejeter les valeurs absurdes /
     * débordement dès la validation. La protection réelle contre un
     * gonflement du stock `vides` d'un dépôt est la garde métier de
     * `CycleLivraison` (`vides_recuperes <= commande.quantite`, audit
     * sécurité Phase 4, [MOYEN]) : `quantite` est elle-même bornée à 100
     * (`CommandeStoreRequest`/`DepotPropositionRequest`), donc aucune
     * commande légitime ne peut jamais approcher cette borne.
     */
    private const int MAX_VIDES_RECUPERES = 10000;

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
            'statut' => ['required', Rule::in(['en_route', 'livree', 'vide_recupere'])],
            'vides_recuperes' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_VIDES_RECUPERES],
        ];
    }
}

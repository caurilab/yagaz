<?php

namespace App\Http\Requests;

use App\Enums\StatutPlateau;
use App\Models\Plateau;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Liaison d'un plateau à une bouteille (contrat API,
 * `POST /api/bouteilles/{uuid}/plateau`) : le plateau doit être actif et
 * posé sur le même site que la bouteille.
 */
class BouteillePlateauRequest extends FormRequest
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
            'plateau_uid' => ['required', 'string', 'exists:plateaux,uid'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $plateauUid = $this->input('plateau_uid');

            if ($plateauUid === null) {
                return;
            }

            $bouteille = $this->route('bouteille');
            $plateau = Plateau::where('uid', $plateauUid)->first();

            if ($plateau === null || $plateau->statut !== StatutPlateau::Actif || $plateau->site_id !== $bouteille?->site_id) {
                $validator->errors()->add('plateau_uid', "Ce plateau n'est pas actif sur le site de la bouteille.");
            }
        });
    }
}

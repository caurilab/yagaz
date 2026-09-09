<?php

namespace App\Http\Requests;

use App\Enums\RoleBouteille;
use App\Enums\StatutPlateau;
use App\Enums\TareSource;
use App\Models\Plateau;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Enregistrement d'une bouteille (contrat API,
 * `POST /api/sites/{uuid}/bouteilles`). `site_id` est dérivé du site de la
 * route (ADR 0005), jamais reçu du client.
 */
class BouteilleStoreRequest extends FormRequest
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
        $clesPiecesAmovibles = array_column(config('bouteille.pieces_amovibles'), 'cle');

        return [
            'format_id' => ['required', 'integer', 'exists:formats_bouteille,id'],
            'tare_g' => ['nullable', 'integer', 'min:0'],
            'tare_source' => ['nullable', Rule::enum(TareSource::class)],
            'role_bouteille' => ['nullable', Rule::enum(RoleBouteille::class)],
            'plateau_uid' => ['nullable', 'string', 'exists:plateaux,uid'],
            // Tare ajustable (pièces manquantes) : clés des pièces amovibles
            // cochées comme manquantes - `config('bouteille.pieces_amovibles')`.
            'pieces_manquantes' => ['sometimes', 'array'],
            'pieces_manquantes.*' => ['string', Rule::in($clesPiecesAmovibles)],
        ];
    }

    /**
     * Vérifie que le plateau désigné est actif et posé sur le même site
     * (contrat API : « lie le plateau si `plateau_uid` fourni »).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $plateauUid = $this->input('plateau_uid');

            if ($plateauUid === null) {
                return;
            }

            $site = $this->route('site');
            $plateau = Plateau::where('uid', $plateauUid)->first();

            if ($plateau === null || $plateau->statut !== StatutPlateau::Actif || $plateau->site_id !== $site?->id) {
                $validator->errors()->add('plateau_uid', "Ce plateau n'est pas actif sur ce site.");
            }
        });
    }
}

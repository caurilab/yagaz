<?php

namespace App\Http\Requests;

use App\Enums\RoleBouteille;
use App\Enums\TareSource;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'une bouteille (contrat API, `PATCH /api/bouteilles/{uuid}`).
 * Passer `role_bouteille=active` permute avec l'ancienne active du site
 * (géré dans le contrôleur, en transaction). `format_id` permet de corriger
 * le format/marque d'une bouteille déjà enregistrée ; `site_id` n'est en
 * revanche jamais saisissable ici (ADR 0005, dérivé côté serveur).
 */
class BouteilleUpdateRequest extends FormRequest
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
            'format_id' => ['sometimes', 'integer', 'exists:formats_bouteille,id'],
            'role_bouteille' => ['sometimes', Rule::enum(RoleBouteille::class)],
            'seuil_bas_pct' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'tare_g' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'tare_source' => ['sometimes', Rule::enum(TareSource::class)],
            // Tare ajustable (pièces manquantes) : recalculée dans le
            // contrôleur si ce champ est fourni.
            'pieces_manquantes' => ['sometimes', 'array'],
            'pieces_manquantes.*' => ['string', Rule::in($clesPiecesAmovibles)],
        ];
    }
}

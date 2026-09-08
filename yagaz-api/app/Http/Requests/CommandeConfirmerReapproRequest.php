<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Confirmation (et ajustement éventuel de la quantité) d'un réappro par le
 * dépôt demandeur (ADR 0009, maillon D, contrat API doc 11 §1,
 * `POST /api/commandes/{uuid}/confirmer-reappro`).
 */
class CommandeConfirmerReapproRequest extends FormRequest
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
            'quantite' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}

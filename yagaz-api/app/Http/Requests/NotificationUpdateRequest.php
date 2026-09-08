<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Marquer une notification comme vue (contrat API doc 11,
 * `PATCH /api/notifications/{id}`) : seul `vue` est accepté ici — `emise`
 * est l'état initial, `resolue` reste réservé à `PATCH /api/alertes/{id}`
 * (périmètre foyer, doc 09).
 */
class NotificationUpdateRequest extends FormRequest
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
            'statut' => ['required', Rule::in(['vue'])],
        ];
    }
}

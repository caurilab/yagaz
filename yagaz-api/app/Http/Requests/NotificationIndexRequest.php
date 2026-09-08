<?php

namespace App\Http\Requests;

use App\Enums\StatutAlerte;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtre des notifications de l'utilisateur courant (contrat API doc 11,
 * `GET /api/notifications?statut=`).
 */
class NotificationIndexRequest extends FormRequest
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
            'statut' => ['sometimes', Rule::enum(StatutAlerte::class)],
        ];
    }
}

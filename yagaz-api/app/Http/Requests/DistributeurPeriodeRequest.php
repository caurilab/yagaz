<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Fenêtre temporelle des agrégats distributeur (contrat API doc 11,
 * `GET /api/distributeurs/{orgUuid}/demande` et `.../volumes`).
 */
class DistributeurPeriodeRequest extends FormRequest
{
    /**
     * Amplitude maximale autorisée entre `depuis` et `jusqua` (audit
     * sécurité, [MOYEN] anti-DoS agrégats distributeur) : sans cette borne,
     * `depuis=1900&jusqua=2100` chargerait tout l'historique des commandes de
     * la branche en mémoire (`AgregationRegionale::commandesDeLaBranche`).
     */
    private const AMPLITUDE_MAX_MOIS = 24;

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
            'depuis' => ['required', 'date'],
            'jusqua' => ['required', 'date', 'after_or_equal:depuis'],
            'pas' => ['sometimes', Rule::in(['jour', 'semaine', 'mois'])],
        ];
    }

    /**
     * Borne l'amplitude `depuis`/`jusqua` à {@see self::AMPLITUDE_MAX_MOIS}
     * mois — vérifié après la validation de base, une fois les deux dates
     * connues valides (sinon rien à comparer).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['depuis', 'jusqua'])) {
                return;
            }

            $depuis = CarbonImmutable::parse((string) $this->input('depuis'));
            $jusqua = CarbonImmutable::parse((string) $this->input('jusqua'));

            if ($depuis->addMonths(self::AMPLITUDE_MAX_MOIS)->lt($jusqua)) {
                $validator->errors()->add('jusqua', 'La période entre "depuis" et "jusqua" ne peut pas dépasser '.self::AMPLITUDE_MAX_MOIS.' mois.');
            }
        });
    }
}

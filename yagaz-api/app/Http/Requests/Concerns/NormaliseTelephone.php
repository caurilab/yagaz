<?php

namespace App\Http\Requests\Concerns;

/**
 * Normalise un numéro de téléphone avant validation (audit sécurité,
 * [MOYEN] normalisation du téléphone) : `telephone` est l'identifiant pivot
 * des foyers (unicité, login, partages, livreur habituel) — sans format
 * stable, la même personne peut créer plusieurs comptes, ou l'unicité peut
 * être contournée par une variante de saisie (espaces, préfixe `00`, etc.).
 *
 * Format retenu (E.164 souple) : un `+` suivi de 7 à 15 chiffres, le premier
 * non nul — `/^\+?[1-9]\d{6,14}$/` — après avoir :
 *  - retiré espaces/points/tirets ;
 *  - converti un préfixe international `00` en `+` ;
 *  - conservé un `+` déjà présent ;
 *  - sinon préfixé un `+` (un numéro sans indicatif de sortie ni `+` est
 *    supposé déjà écrit indicatif+national, ex. `221770000001`).
 * Les trois variantes `+221770000001`, `00221770000001` et
 * `221 77 000 00 01` désignent donc le même numéro normalisé.
 */
trait NormaliseTelephone
{
    /**
     * Retire espaces/points/tirets, convertit un préfixe `00` en `+`, et
     * garantit un `+` initial. `null` passe inchangé (champ absent ou
     * optionnel).
     */
    protected function normaliserTelephone(?string $telephone): ?string
    {
        if ($telephone === null) {
            return null;
        }

        $telephone = trim($telephone);
        $telephone = preg_replace('/[\s.\-]+/', '', $telephone) ?? $telephone;

        if (str_starts_with($telephone, '00')) {
            $telephone = '+'.substr($telephone, 2);
        } elseif ($telephone !== '' && $telephone[0] !== '+') {
            $telephone = '+'.$telephone;
        }

        return $telephone;
    }

    /**
     * Règle de validation du format E.164 souple, appliquée après
     * normalisation (donc sur la valeur déjà nettoyée).
     */
    protected function regleFormatTelephone(): string
    {
        return 'regex:/^\+?[1-9]\d{6,14}$/';
    }
}

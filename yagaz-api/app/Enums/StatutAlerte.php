<?php

namespace App\Enums;

/**
 * Statut du cycle de vie d'une alerte (doc 07, §8 `alertes`).
 */
enum StatutAlerte: string
{
    case Emise = 'emise';
    case Vue = 'vue';
    case Resolue = 'resolue';
}

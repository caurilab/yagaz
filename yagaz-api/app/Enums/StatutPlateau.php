<?php

namespace App\Enums;

/**
 * Statut du cycle de vie d'un plateau (doc 07, §4 `plateaux`).
 */
enum StatutPlateau: string
{
    case Provisionne = 'provisionne';
    case Actif = 'actif';
    case HorsService = 'hors_service';
}

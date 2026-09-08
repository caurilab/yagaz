<?php

namespace App\Enums;

/**
 * Origine de la valeur de tare d'une bouteille (doc 07, §4 `bouteilles`).
 */
enum TareSource: string
{
    case Nominale = 'nominale';
    case Saisie = 'saisie';
    case Calibree = 'calibree';
}

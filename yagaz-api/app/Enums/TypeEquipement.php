<?php

namespace App\Enums;

/**
 * Type d'équipement du registre unifié `equipements` (ADR 0012).
 */
enum TypeEquipement: string
{
    case Balance = 'balance';
    case Temperature = 'temperature';
    case Ecran = 'ecran';
}

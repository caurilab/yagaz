<?php

namespace App\Enums;

/**
 * Statut du cycle de vie d'un équipement du registre `equipements`
 * (ADR 0012) : tant qu'il n'est pas `actif`, la capacité de site
 * correspondante (`a_balance`/`a_temperature`/`a_ecran`) reste fausse.
 */
enum StatutEquipement: string
{
    case AConnecter = 'a_connecter';
    case Actif = 'actif';
    case HorsService = 'hors_service';
}

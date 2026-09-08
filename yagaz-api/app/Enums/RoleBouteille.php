<?php

namespace App\Enums;

/**
 * Rôle d'une bouteille sur un site (doc 07, §4 `bouteilles`).
 *
 * Au plus une bouteille `Active` par site (index unique partiel, voir migration
 * `create_bouteilles_table`).
 */
enum RoleBouteille: string
{
    case Active = 'active';
    case Secours = 'secours';
}

<?php

namespace App\Enums;

/**
 * Niveau d'accès d'un utilisateur à un site (doc 07, §3 `site_acces`).
 */
enum NiveauAcces: string
{
    case Proprietaire = 'proprietaire';
    case Gestionnaire = 'gestionnaire';
    case Observateur = 'observateur';
}

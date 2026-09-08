<?php

namespace App\Enums;

/**
 * Source d'alimentation électrique d'un plateau (doc 07, §4 `plateaux`).
 *
 * Non listé explicitement dans la commande de la Phase 1 mais présent au doc 07 ;
 * ajouté par cohérence avec les autres enums de la table `plateaux`.
 */
enum AlimPlateau: string
{
    case Secteur = 'secteur';
    case Batterie = 'batterie';
}

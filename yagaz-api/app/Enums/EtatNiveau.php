<?php

namespace App\Enums;

/**
 * État visuel commun du niveau d'une bouteille (contrat API, §« États
 * visuels de niveau »), calculé côté serveur pour un langage identique sur
 * l'app et le web.
 */
enum EtatNiveau: string
{
    case Plein = 'plein';
    case Correct = 'correct';
    case Bas = 'bas';
    case PresqueVide = 'presque_vide';
    case Inconnu = 'inconnu';
}

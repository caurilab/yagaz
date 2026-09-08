<?php

namespace App\Enums;

/**
 * Issue du traitement d'un message de température de cuisine (ADR 0011) —
 * utilisé par le résultat de `App\Services\Temperature\TraitementTemperature`
 * pour rendre le pipeline testable sans dépendre du transport MQTT (calqué
 * sur `StatutIngestionMesure`).
 */
enum StatutIngestionTemperature: string
{
    /** La température a été rangée (cuisson/alerte évaluées le cas échéant). */
    case Rangee = 'rangee';

    /** Message déjà vu (même couple plateau/seq) : ignoré, rien de rangé. */
    case Doublon = 'doublon';

    /** Plateau inconnu/non actif/sans site, ou message invalide : rien de rangé. */
    case Rejetee = 'rejetee';
}

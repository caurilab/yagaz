<?php

namespace App\Enums;

/**
 * Issue du traitement d'un message de mesure (doc 08 §1-3) — utilisé par le
 * résultat de `App\Services\Mesure\TraitementMesure` pour rendre le pipeline
 * testable sans dépendre du transport MQTT.
 */
enum StatutIngestionMesure: string
{
    /** La mesure a été rangée (et, le cas échéant, le niveau recalculé). */
    case Rangee = 'rangee';

    /** Message déjà vu ou `seq` en régression : ignoré, rien de rangé. */
    case Doublon = 'doublon';

    /** Plateau inconnu/non actif ou message invalide : rien de rangé. */
    case Rejetee = 'rejetee';
}

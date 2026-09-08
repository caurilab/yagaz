<?php

namespace App\Enums;

/**
 * Canal de notification d'une alerte (doc 07, §8 `alertes`).
 */
enum CanalAlerte: string
{
    case Push = 'push';
    case Sms = 'sms';
    case Whatsapp = 'whatsapp';
}

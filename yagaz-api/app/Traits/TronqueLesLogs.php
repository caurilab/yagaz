<?php

namespace App\Traits;

/**
 * Assainit une valeur externe (payload MQTT, topic…) avant de la journaliser
 * (audit sécurité, correctif #4) : le payload brut complet ne doit jamais
 * être écrit tel quel dans les logs (taille, injection de retours-ligne). On
 * tronque à 200 caractères et on retire les caractères de contrôle
 * (retours-ligne compris) qui permettraient de forger de fausses lignes de
 * log.
 */
trait TronqueLesLogs
{
    private function tronquerPourLog(string $valeur): string
    {
        $assainie = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $valeur) ?? $valeur;

        return mb_substr($assainie, 0, 200);
    }
}

<?php

namespace App\Services\Notification;

use App\Contracts\Notification\CanalNotification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Implémentation par défaut de `CanalNotification` (contrat API doc 11, §3) :
 * journalise l'envoi plutôt que de le réaliser réellement. Suffisant en v1 —
 * un vrai provider (FCM/APNs, SMS, WhatsApp) se substituera à cette classe
 * sans changer `Notificateur`.
 */
final class CanalLog implements CanalNotification
{
    public function envoyer(User $destinataire, string $type, array $donnees): void
    {
        Log::info('yagaz.notification', [
            'destinataire_user_id' => $destinataire->id,
            'type' => $type,
        ] + $donnees);
    }
}

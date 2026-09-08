<?php

namespace App\Contracts\Notification;

use App\Models\User;

/**
 * Un canal d'envoi effectif d'une notification (contrat API doc 11, §3) :
 * push, SMS, WhatsApp… En v1, seule l'implémentation par défaut (`CanalLog`,
 * qui journalise) existe ; les vrais providers (FCM/APNs, SMS, WhatsApp) se
 * substitueront à elle — ou se composeront avec elle — sans changer
 * `Notificateur` ni le code appelant.
 */
interface CanalNotification
{
    /**
     * Envoie une notification. Ne doit jamais lever d'exception si le canal
     * réel est indisponible (sobriété/robustesse, doc 11 §3) : au pire,
     * journaliser l'échec en interne.
     *
     * @param  array<string, mixed>  $donnees
     */
    public function envoyer(User $destinataire, string $type, array $donnees): void;
}

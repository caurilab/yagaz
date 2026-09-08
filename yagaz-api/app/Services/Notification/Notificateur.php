<?php

namespace App\Services\Notification;

use App\Contracts\Notification\CanalNotification;
use App\Enums\CanalAlerte;
use App\Enums\NiveauAcces;
use App\Enums\StatutAlerte;
use App\Enums\TypeAlerte;
use App\Models\Alerte;
use App\Models\Bouteille;
use App\Models\Commande;
use App\Models\Organisation;
use App\Models\Site;
use App\Models\User;

/**
 * Point d'entrée unique pour adresser une notification (contrat API doc 11,
 * §3) : crée l'`Alerte` adressée (traçabilité, lisible via
 * `GET /api/notifications`) puis route vers le(s) canal(aux) selon les
 * préférences de l'utilisateur (`users.canaux_alerte`). Ne bloque jamais si
 * aucun canal réel n'est configuré — `CanalLog` (par défaut) se contente de
 * journaliser (sobriété/robustesse).
 */
final class Notificateur
{
    public function __construct(private readonly CanalNotification $canal = new CanalLog) {}

    /**
     * Crée une alerte adressée à `$destinataire` et la route vers son (ses)
     * canal(aux) préféré(s) (défaut `push` si aucune préférence enregistrée).
     * `$donnees` n'est jamais persisté sur l'`Alerte` (pas de colonne dédiée,
     * doc 11 §3) : transmis tel quel au(x) canal(aux), pour le contenu du
     * message.
     *
     * @param  array<string, mixed>  $donnees
     */
    public function notifier(
        User $destinataire,
        TypeAlerte $type,
        array $donnees = [],
        ?Bouteille $bouteille = null,
        ?Organisation $organisation = null,
        ?Commande $commande = null,
        ?Site $site = null,
    ): Alerte {
        $canauxPreferes = ! empty($destinataire->canaux_alerte) ? $destinataire->canaux_alerte : [CanalAlerte::Push->value];
        $canalPrincipal = CanalAlerte::tryFrom((string) $canauxPreferes[0]) ?? CanalAlerte::Push;

        $alerte = Alerte::create([
            'bouteille_id' => $bouteille?->id,
            'organisation_id' => $organisation?->id,
            'commande_id' => $commande?->id,
            'site_id' => $site?->id,
            'destinataire_user_id' => $destinataire->id,
            'type' => $type,
            'statut' => StatutAlerte::Emise,
            'canal' => $canalPrincipal,
        ]);

        foreach ($canauxPreferes as $canal) {
            $this->canal->envoyer($destinataire, $type->value, $donnees + ['canal' => $canal, 'alerte_id' => $alerte->id]);
        }

        return $alerte;
    }

    /**
     * Résout le foyer destinataire d'une commande (doc 11 §3) : le demandeur
     * s'il existe (commande créée par un foyer), sinon le propriétaire du
     * site de livraison — cas d'une proposition dépôt→foyer (doc 10 §2-3),
     * où `demandeur_user_id` reste `null` même une fois la commande acceptée.
     */
    public function resoudreDestinataireFoyer(Commande $commande): ?User
    {
        if ($commande->demandeur_user_id !== null) {
            return $commande->demandeurUser;
        }

        return $this->proprietaireDuSite($commande->site);
    }

    /**
     * Propriétaire d'un site (niveau `proprietaire` de `site_acces`), ou
     * `null` si le site n'a pas (encore) de propriétaire identifiable.
     */
    public function proprietaireDuSite(?Site $site): ?User
    {
        if ($site === null) {
            return null;
        }

        return $site->siteAcces()
            ->where('niveau', NiveauAcces::Proprietaire->value)
            ->with('user')
            ->first()?->user;
    }
}

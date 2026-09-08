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
use Illuminate\Support\Facades\Log;

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
     * **Invariant anti-fuite** (audit sécurité, [INFO] garde notificateur,
     * doc 04 §8, doc 07 §9) : une alerte ne doit jamais porter de référence
     * foyer (`site`/`bouteille`) vers un destinataire qui n'a pas accès à ce
     * site. Tous les appelants actuels adressent le propriétaire du site
     * (`resoudreDestinataireFoyer()`/`proprietaireDuSite()`), donc l'accès
     * est toujours vrai en pratique — cette garde protège un appelant futur
     * qui adresserait un tiers (ex. livreur) sans le faire explicitement.
     * Si l'accès manque, les références `site`/`bouteille` sont omises et un
     * warning est journalisé plutôt que d'échouer : l'alerte (et la
     * notification) reste créée, sans fuite de donnée de foyer.
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
        $siteEffectif = $site ?? $bouteille?->site;

        if ($siteEffectif !== null && ! $destinataire->aAccesAuSite($siteEffectif)) {
            Log::warning('Notificateur: destinataire sans accès au site, références foyer omises.', [
                'destinataire_user_id' => $destinataire->id,
                'site_id' => $siteEffectif->id,
                'type' => $type->value,
            ]);

            $bouteille = null;
            $site = null;
        }

        $alerte = Alerte::create([
            'bouteille_id' => $bouteille?->id,
            'organisation_id' => $organisation?->id,
            'commande_id' => $commande?->id,
            'site_id' => $site?->id,
            'destinataire_user_id' => $destinataire->id,
            'type' => $type,
            'statut' => StatutAlerte::Emise,
            'canal' => $this->canalPrincipal($destinataire),
        ]);

        $this->router($destinataire, $type, $donnees, $alerte);

        return $alerte;
    }

    /**
     * Notifie le livreur habituel d'un site en tension (ADR 0009, maillon A ;
     * ADR 0008, étanchéité) avec l'information MINIMALE nécessaire pour agir —
     * jamais le niveau exact, l'autonomie, l'historique de consommation, ni
     * les autres bouteilles/sites du foyer. L'alerte créée ne porte donc
     * JAMAIS de référence foyer (`bouteille_id`/`site_id` restent `null`,
     * contrairement à `notifier()`) : seule `contexte` (colonne dédiée) porte
     * cette projection minimale. On ne s'appuie pas ici sur l'invariant
     * anti-fuite de `notifier()` (qui retirerait ces références de toute
     * façon puisque le livreur n'a pas d'accès au site) : l'omission est
     * explicite, pas un effet de bord.
     *
     * Anti-spam (ADR 0009) : pas de doublon tant qu'une alerte `seuil_bas`
     * non résolue existe déjà pour ce livreur portant le même `site_nom` —
     * la seule clé de rapprochement disponible ici, puisque `site_id` n'est
     * justement jamais renseigné sur ce type d'alerte.
     *
     * @param  array{site_nom: ?string, zone: ?string, format_code: ?string}  $contexteMinimal
     */
    public function notifierLivreurHabituel(User $livreur, array $contexteMinimal): ?Alerte
    {
        $dejaNotifie = Alerte::where('destinataire_user_id', $livreur->id)
            ->where('type', TypeAlerte::SeuilBas)
            ->where('statut', '!=', StatutAlerte::Resolue)
            ->where('contexte->site_nom', $contexteMinimal['site_nom'] ?? null)
            ->exists();

        if ($dejaNotifie) {
            return null;
        }

        $alerte = Alerte::create([
            'destinataire_user_id' => $livreur->id,
            'type' => TypeAlerte::SeuilBas,
            'statut' => StatutAlerte::Emise,
            'canal' => $this->canalPrincipal($livreur),
            'contexte' => $contexteMinimal,
        ]);

        $this->router($livreur, TypeAlerte::SeuilBas, $contexteMinimal, $alerte);

        return $alerte;
    }

    /**
     * Canal principal (le premier des préférences du destinataire, `push`
     * par défaut), enregistré sur l'alerte pour traçabilité.
     */
    private function canalPrincipal(User $destinataire): CanalAlerte
    {
        $canauxPreferes = ! empty($destinataire->canaux_alerte) ? $destinataire->canaux_alerte : [CanalAlerte::Push->value];

        return CanalAlerte::tryFrom((string) $canauxPreferes[0]) ?? CanalAlerte::Push;
    }

    /**
     * Route vers le(s) canal(aux) préféré(s) du destinataire (défaut `push`
     * si aucune préférence enregistrée).
     *
     * @param  array<string, mixed>  $donnees
     */
    private function router(User $destinataire, TypeAlerte $type, array $donnees, Alerte $alerte): void
    {
        $canauxPreferes = ! empty($destinataire->canaux_alerte) ? $destinataire->canaux_alerte : [CanalAlerte::Push->value];

        foreach ($canauxPreferes as $canal) {
            $this->canal->envoyer($destinataire, $type->value, $donnees + ['canal' => $canal, 'alerte_id' => $alerte->id]);
        }
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

<?php

namespace App\Services\Commande;

use App\Enums\RoleBouteille;
use App\Models\Bouteille;
use App\Models\Site;

/**
 * Détecte la tension d'un site (ADR 0009, maillons B et C) : une bouteille
 * **active** dont le niveau courant est sous son seuil bas
 * (`niveaux_courants.niveau_pct < bouteilles.seuil_bas_pct`) — même critère
 * que la file du dépôt et le droit de proposer du livreur habituel, pour que
 * les deux maillons restent cohérents.
 */
final class DetectionTension
{
    /**
     * La bouteille active en tension du site, ou `null` si le site n'est pas
     * en tension (pas de bouteille active, ou niveau au-dessus du seuil).
     * Au plus une bouteille active par site (index unique partiel).
     */
    public function bouteilleEnTension(Site $site): ?Bouteille
    {
        return Bouteille::where('site_id', $site->id)
            ->where('role_bouteille', RoleBouteille::Active)
            ->whereHas('niveauCourant', fn ($query) => $query
                ->whereColumn('niveaux_courants.niveau_pct', '<', 'bouteilles.seuil_bas_pct'))
            ->with(['format', 'niveauCourant'])
            ->first();
    }

    /**
     * Vrai si le site a au moins une bouteille active en tension.
     */
    public function siteEnTension(Site $site): bool
    {
        return $this->bouteilleEnTension($site) !== null;
    }
}

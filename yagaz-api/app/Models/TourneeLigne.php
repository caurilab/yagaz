<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne d'une tournée : pour un dépôt et un format donnés, les bouteilles
 * pleines à déposer et les vides à récupérer (Phase 5, contrat API doc 11,
 * §1 — logistique inversée). Toujours créée par `CycleTournee` à partir d'un
 * tableau curaté (dépôt résolu et vérifié rattaché au mandataire), jamais
 * directement depuis la requête (ADR 0005) — `#[Guarded([])]` par cohérence
 * avec `Alerte`/`MouvementStock`, autres journaux posés uniquement par un
 * service.
 */
#[Guarded([])]
class TourneeLigne extends Model
{
    protected $table = 'tournee_lignes';

    /**
     * @return BelongsTo<Tournee, $this>
     */
    public function tournee(): BelongsTo
    {
        return $this->belongsTo(Tournee::class);
    }

    /**
     * @return BelongsTo<Organisation, $this>
     */
    public function depot(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'depot_organisation_id');
    }

    /**
     * @return BelongsTo<FormatBouteille, $this>
     */
    public function format(): BelongsTo
    {
        return $this->belongsTo(FormatBouteille::class, 'format_id');
    }
}

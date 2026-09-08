<?php

namespace App\Services\Tournee;

use App\Enums\RoleMembership;
use App\Enums\StatutTournee;
use App\Models\Organisation;
use App\Models\Tournee;
use App\Models\TourneeLigne;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Centralise le cycle de vie d'une tournée de mandataire (contrat API
 * doc 11, §1) : création/validation à partir de lignes {dépôt, format,
 * pleines, vides à récupérer} — logistique inversée —, puis ajustement
 * (statut, livreur, lignes). La plateforme **propose** (via `GET .../reappros`,
 * `DepotCommandeController`/`CommandeController`), le mandataire **valide** en
 * appelant `creerEtValider` ; il peut ensuite **ajuster** via `ajuster`.
 */
final class CycleTournee
{
    /**
     * Ordre des statuts, pour n'autoriser qu'une progression (jamais un
     * retour en arrière) — plus souple que `CycleLivraison::ORDRE` (qui
     * n'autorise que le pas suivant immédiat) : une tournée peut sauter un
     * palier (ex. `proposee` → `en_cours` si le mandataire valide et démarre
     * dans le même geste).
     *
     * @var array<string, int>
     */
    private const array ORDRE = [
        'proposee' => 0,
        'validee' => 1,
        'en_cours' => 2,
        'terminee' => 3,
    ];

    /**
     * Crée une tournée `validee` pour le mandataire, avec ses lignes (doc 11,
     * §1 : « la plateforme propose, le mandataire valide/ajuste »).
     *
     * @param  array<int, array{depot_uuid: string, format_id: int, pleines: int, vides_a_recuperer: int}>  $lignes
     */
    public function creerEtValider(Organisation $mandataire, CarbonInterface|string $date, ?User $livreur, array $lignes): Tournee
    {
        $this->verifierLivreur($mandataire, $livreur);

        return DB::transaction(function () use ($mandataire, $date, $livreur, $lignes) {
            $tournee = new Tournee;
            $tournee->forceFill([
                'organisation_id' => $mandataire->id,
                'livreur_user_id' => $livreur?->id,
                'date' => $date,
                'statut' => StatutTournee::Validee,
            ]);
            $tournee->save();

            $this->remplacerLignes($tournee, $mandataire, $lignes);

            return $tournee;
        });
    }

    /**
     * Ajuste une tournée existante (doc 11, §1,
     * `PATCH /api/tournees/{uuid}`) : statut (progression uniquement),
     * livreur, et/ou lignes (remplacement complet si fournies).
     *
     * @param  ?array<int, array{depot_uuid: string, format_id: int, pleines: int, vides_a_recuperer: int}>  $lignes
     */
    public function ajuster(
        Tournee $tournee,
        ?StatutTournee $statut,
        ?User $livreur,
        bool $livreurFourni,
        ?array $lignes,
    ): Tournee {
        $mandataire = $tournee->organisation;

        if ($livreurFourni) {
            $this->verifierLivreur($mandataire, $livreur);
        }

        return DB::transaction(function () use ($tournee, $mandataire, $statut, $livreur, $livreurFourni, $lignes) {
            $tourneeVerrouillee = Tournee::whereKey($tournee->id)->lockForUpdate()->firstOrFail();

            if ($livreurFourni) {
                $tourneeVerrouillee->livreur_user_id = $livreur?->id;
            }

            if ($statut !== null) {
                $rangActuel = self::ORDRE[$tourneeVerrouillee->statut->value];
                $rangNouveau = self::ORDRE[$statut->value];

                abort_if($rangNouveau < $rangActuel, 422, "Impossible de reculer le statut d'une tournée.");

                $tourneeVerrouillee->statut = $statut;
            }

            $tourneeVerrouillee->save();

            if ($lignes !== null && $mandataire !== null) {
                $this->remplacerLignes($tourneeVerrouillee, $mandataire, $lignes);
            }

            return $tourneeVerrouillee->fresh();
        });
    }

    /**
     * Le livreur, s'il est précisé, doit être membre `livreur` du mandataire
     * (même garde-fou que `CycleCommande::affecterLivreur` côté dépôt).
     */
    private function verifierLivreur(?Organisation $mandataire, ?User $livreur): void
    {
        if ($livreur === null) {
            return;
        }

        abort_unless(
            $mandataire !== null && $livreur->estMembreDe($mandataire, RoleMembership::Livreur),
            422,
            "Cet utilisateur n'est pas livreur de ce mandataire."
        );
    }

    /**
     * Remplace intégralement les lignes de la tournée. Chaque dépôt doit être
     * rattaché au mandataire (`parent_id`) — sinon 422 : un mandataire ne
     * planifie pas de tournée vers un dépôt hors de son périmètre.
     *
     * @param  array<int, array{depot_uuid: string, format_id: int, pleines: int, vides_a_recuperer: int}>  $lignes
     */
    private function remplacerLignes(Tournee $tournee, Organisation $mandataire, array $lignes): void
    {
        $tournee->lignes()->delete();

        foreach ($lignes as $ligne) {
            $depot = Organisation::where('uuid', $ligne['depot_uuid'])
                ->where('parent_id', $mandataire->id)
                ->first();

            abort_if($depot === null, 422, "Ce dépôt n'est pas rattaché à ce mandataire.");

            TourneeLigne::create([
                'tournee_id' => $tournee->id,
                'depot_organisation_id' => $depot->id,
                'format_id' => $ligne['format_id'],
                'pleines' => $ligne['pleines'],
                'vides_a_recuperer' => $ligne['vides_a_recuperer'],
            ]);
        }
    }
}

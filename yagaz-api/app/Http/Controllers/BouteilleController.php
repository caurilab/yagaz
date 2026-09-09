<?php

namespace App\Http\Controllers;

use App\Enums\RoleBouteille;
use App\Enums\TareSource;
use App\Http\Controllers\Concerns\AutoriseCloisonnement;
use App\Http\Requests\BouteillePlateauRequest;
use App\Http\Requests\BouteilleStoreRequest;
use App\Http\Requests\BouteilleUpdateRequest;
use App\Http\Resources\BouteilleResource;
use App\Models\Bouteille;
use App\Models\FormatBouteille;
use App\Models\Plateau;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Bouteilles du périmètre foyer (contrat API, §« Bouteilles »). La
 * permutation active/secours (§« Règles métier sensibles ») est appliquée en
 * transaction pour respecter l'index unique partiel `bouteille_active_unique`.
 */
class BouteilleController extends Controller
{
    use AutoriseCloisonnement;

    private const array RELATIONS = ['format.marqueRef', 'plateau', 'niveauCourant'];

    public function index(Request $request, Site $site): AnonymousResourceCollection
    {
        abort_unless($request->user()->can('view', $site), 404);

        $bouteilles = $site->bouteilles()
            ->with(self::RELATIONS)
            ->get()
            ->sortByDesc(fn (Bouteille $bouteille) => $bouteille->role_bouteille === RoleBouteille::Active)
            ->values();

        return BouteilleResource::collection($bouteilles);
    }

    public function store(BouteilleStoreRequest $request, Site $site): JsonResponse
    {
        $this->autoriserSite($request->user(), $site, 'update');

        $validated = $request->validated();

        $bouteille = DB::transaction(function () use ($validated, $site) {
            $premiereBouteille = ! Bouteille::where('site_id', $site->id)->exists();

            $roleSouhaite = isset($validated['role_bouteille'])
                ? RoleBouteille::from($validated['role_bouteille'])
                : ($premiereBouteille ? RoleBouteille::Active : RoleBouteille::Secours);

            $piecesManquantes = $validated['pieces_manquantes'] ?? null;
            $tareG = $validated['tare_g'] ?? null;

            if (! empty($piecesManquantes) && $tareG === null) {
                // Tare ajustable (pièces manquantes) : ne s'applique que si
                // le client n'a pas déjà saisi une tare explicite - le
                // mécanisme prime sur les grammages par défaut, mais jamais
                // sur une saisie directe de tare.
                $tareG = $this->tareAjustee($validated['format_id'], $piecesManquantes);
                $tareSource = TareSource::Saisie;
            } else {
                $tareSource = isset($validated['tare_source'])
                    ? TareSource::from($validated['tare_source'])
                    : ($tareG !== null ? TareSource::Saisie : TareSource::Nominale);
            }

            // `site_id` est dérivé du site de la route (ADR 0005), jamais du
            // corps de la requête : posé par affectation directe de
            // propriété plutôt que par le mass assignment de `create()`, car
            // `Bouteille` ne déclare pas `site_id` comme fillable (audit
            // sécurité, [INFO] $fillable explicite).
            $bouteille = new Bouteille([
                'format_id' => $validated['format_id'],
                'tare_g' => $tareG,
                'tare_source' => $tareSource,
                'tare_fiable' => $tareSource === TareSource::Saisie,
                // Toujours créée en secours : la promotion (avec démotion de
                // l'ancienne active dans la même transaction) est appliquée
                // juste après si nécessaire, pour ne jamais violer l'index
                // unique partiel « une seule active par site ».
                'role_bouteille' => RoleBouteille::Secours,
                'pieces_manquantes' => $piecesManquantes,
            ]);
            $bouteille->site_id = $site->id;
            $bouteille->save();

            if ($roleSouhaite === RoleBouteille::Active) {
                $this->promouvoirActive($bouteille);
            }

            if (isset($validated['plateau_uid'])) {
                $plateau = Plateau::where('uid', $validated['plateau_uid'])->firstOrFail();
                $this->lierPlateau($bouteille, $plateau);
            }

            return $bouteille;
        });

        return (new BouteilleResource($bouteille->fresh(self::RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Bouteille $bouteille): BouteilleResource
    {
        abort_unless($request->user()->can('view', $bouteille), 404);

        return new BouteilleResource($bouteille->load(self::RELATIONS));
    }

    public function update(BouteilleUpdateRequest $request, Bouteille $bouteille): BouteilleResource
    {
        $this->autoriserBouteille($request->user(), $bouteille, 'update');

        $validated = $request->validated();

        DB::transaction(function () use ($validated, $bouteille): void {
            if (array_key_exists('format_id', $validated)) {
                $bouteille->format_id = $validated['format_id'];
            }

            if (array_key_exists('role_bouteille', $validated)) {
                $role = RoleBouteille::from($validated['role_bouteille']);

                if ($role === RoleBouteille::Active) {
                    $this->promouvoirActive($bouteille);
                } else {
                    $bouteille->role_bouteille = $role;
                }
            }

            if (array_key_exists('seuil_bas_pct', $validated)) {
                $bouteille->seuil_bas_pct = $validated['seuil_bas_pct'];
            }

            if (array_key_exists('tare_source', $validated)) {
                $tareSource = TareSource::from($validated['tare_source']);
                $bouteille->tare_source = $tareSource;
                $bouteille->tare_fiable = $tareSource === TareSource::Saisie;
            } elseif (array_key_exists('tare_g', $validated)) {
                // Une tare saisie manuellement sans précision de source est
                // considérée fiable immédiatement (pesée directe du foyer).
                $bouteille->tare_source = TareSource::Saisie;
                $bouteille->tare_fiable = true;
            }

            if (array_key_exists('tare_g', $validated)) {
                $bouteille->tare_g = $validated['tare_g'];
            }

            if (array_key_exists('pieces_manquantes', $validated)) {
                $bouteille->pieces_manquantes = $validated['pieces_manquantes'];

                // Tare ajustable (pièces manquantes) : recalcule à partir du
                // format (déjà mis à jour ci-dessus le cas échéant), sauf si
                // le client a saisi une tare explicite dans la même requête
                // - le mécanisme prime sur les grammages par défaut, jamais
                // sur une saisie directe de tare.
                if (! array_key_exists('tare_g', $validated) && $validated['pieces_manquantes'] !== []) {
                    $tareAjustee = $this->tareAjustee($bouteille->format_id, $validated['pieces_manquantes']);

                    if ($tareAjustee !== null) {
                        $bouteille->tare_g = $tareAjustee;
                        $bouteille->tare_source = TareSource::Saisie;
                        $bouteille->tare_fiable = true;
                    }
                }
            }

            $bouteille->save();
        });

        return new BouteilleResource($bouteille->fresh(self::RELATIONS));
    }

    public function attacherPlateau(BouteillePlateauRequest $request, Bouteille $bouteille): BouteilleResource
    {
        $this->autoriserBouteille($request->user(), $bouteille, 'update');

        $plateau = Plateau::where('uid', $request->validated('plateau_uid'))->firstOrFail();
        $this->lierPlateau($bouteille, $plateau);

        return new BouteilleResource($bouteille->fresh(self::RELATIONS));
    }

    public function detacherPlateau(Request $request, Bouteille $bouteille): BouteilleResource
    {
        $this->autoriserBouteille($request->user(), $bouteille, 'update');

        $bouteille->plateau_id = null;
        $bouteille->save();

        return new BouteilleResource($bouteille->fresh(self::RELATIONS));
    }

    public function destroy(Request $request, Bouteille $bouteille): Response
    {
        $this->autoriserBouteille($request->user(), $bouteille, 'delete');

        $bouteille->delete();

        return response()->noContent();
    }

    /**
     * Promeut la bouteille en active, en démotant l'ancienne active du
     * même site dans la même transaction (contrat API, §« Règles métier
     * sensibles ») : la démotion précède toujours la promotion, pour ne
     * jamais avoir deux bouteilles actives en même temps sur le site et
     * respecter l'index unique partiel.
     */
    private function promouvoirActive(Bouteille $bouteille): void
    {
        Bouteille::where('site_id', $bouteille->site_id)
            ->where('role_bouteille', RoleBouteille::Active->value)
            ->where('id', '!=', $bouteille->id)
            ->update(['role_bouteille' => RoleBouteille::Secours->value]);

        $bouteille->role_bouteille = RoleBouteille::Active;
        $bouteille->save();
    }

    /**
     * Tare ajustable (pièces manquantes) : part de la tare de référence du
     * format et retranche la somme des poids des pièces manquantes
     * reconnues (`config('bouteille.pieces_amovibles')`), bornée à 0.
     * Retourne `null` si le format est introuvable.
     *
     * @param  array<int, string>  $piecesManquantes
     */
    private function tareAjustee(int $formatId, array $piecesManquantes): ?int
    {
        $tareNominale = FormatBouteille::find($formatId)?->tare_nominale_g;

        if ($tareNominale === null) {
            return null;
        }

        $piecesAmovibles = collect(config('bouteille.pieces_amovibles'))->keyBy('cle');

        $deltaTotal = collect($piecesManquantes)
            ->sum(fn (string $cle) => $piecesAmovibles->get($cle)['delta_g'] ?? 0);

        return max(0, $tareNominale - $deltaTotal);
    }

    /**
     * Lie un plateau à une bouteille, en le déliant d'abord d'une éventuelle
     * autre bouteille (un plateau ne porte qu'une bouteille à la fois).
     */
    private function lierPlateau(Bouteille $bouteille, Plateau $plateau): void
    {
        Bouteille::where('plateau_id', $plateau->id)
            ->where('id', '!=', $bouteille->id)
            ->update(['plateau_id' => null]);

        $bouteille->plateau_id = $plateau->id;
        $bouteille->save();
    }
}

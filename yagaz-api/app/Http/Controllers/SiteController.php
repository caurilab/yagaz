<?php

namespace App\Http\Controllers;

use App\Enums\NiveauAcces;
use App\Enums\StatutAlerte;
use App\Http\Controllers\Concerns\AutoriseCloisonnement;
use App\Http\Requests\SiteLivreurHabituelRequest;
use App\Http\Requests\SitePartageRequest;
use App\Http\Requests\SiteStoreRequest;
use App\Http\Requests\SiteUpdateRequest;
use App\Http\Resources\SiteResource;
use App\Http\Resources\UserPubliqueResource;
use App\Models\Alerte;
use App\Models\LivreurHabituel;
use App\Models\Site;
use App\Models\SiteAcces;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Sites du périmètre foyer (contrat API, §« Sites »). Cloisonné par
 * `site_acces` (doc 07, §3 et §10) : `SitePolicy` + `AutoriseCloisonnement`
 * distinguent 404 (aucun accès) de 403 (accès insuffisant).
 */
class SiteController extends Controller
{
    use AutoriseCloisonnement;

    public function index(Request $request): AnonymousResourceCollection
    {
        $sites = $request->user()->sites()->get();
        $sites->each(fn (Site $site) => $this->hydrater($site, $request->user()));

        return SiteResource::collection($sites);
    }

    public function store(SiteStoreRequest $request): JsonResponse
    {
        // `cree_par` est dérivé côté serveur (ADR 0005) : `forceFill` plutôt
        // que le mass assignment de `create()`, car `Site` ne déclare comme
        // fillable que les champs saisissables par le client (audit
        // sécurité, [INFO] $fillable explicite).
        $site = new Site($request->validated());
        $site->forceFill(['cree_par' => $request->user()->id]);
        $site->save();

        SiteAcces::forceCreate([
            'site_id' => $site->id,
            'user_id' => $request->user()->id,
            'niveau' => NiveauAcces::Proprietaire->value,
        ]);

        return (new SiteResource($this->hydrater($site, $request->user())))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Site $site): SiteResource
    {
        abort_unless($request->user()->can('view', $site), 404);

        return new SiteResource($this->hydrater($site, $request->user()));
    }

    public function update(SiteUpdateRequest $request, Site $site): SiteResource
    {
        $this->autoriserSite($request->user(), $site, 'update');

        $site->update($request->validated());

        return new SiteResource($this->hydrater($site->fresh(), $request->user()));
    }

    public function partager(SitePartageRequest $request, Site $site): JsonResponse
    {
        $this->autoriserSite($request->user(), $site, 'partager');

        $beneficiaire = User::where('telephone', $request->validated('telephone'))->firstOrFail();
        $niveau = NiveauAcces::from($request->validated('niveau'));

        $acces = SiteAcces::where('site_id', $site->id)->where('user_id', $beneficiaire->id)->first();

        if ($acces !== null) {
            // Un site doit toujours garder au moins un propriétaire (audit
            // sécurité, [FAIBLE] protection du dernier propriétaire) :
            // rétrograder le dernier propriétaire est refusé, comme le
            // retirer.
            if ($acces->niveau === NiveauAcces::Proprietaire
                && $niveau !== NiveauAcces::Proprietaire
                && $this->estDernierProprietaire($site)) {
                abort(422, 'Impossible de rétrograder le dernier propriétaire du site.');
            }

            $acces->forceFill(['niveau' => $niveau])->save();
        } else {
            SiteAcces::forceCreate([
                'site_id' => $site->id,
                'user_id' => $beneficiaire->id,
                'niveau' => $niveau,
            ]);
        }

        return response()->json([
            'message' => 'Accès partagé.',
            // Projection minimale pour un tiers (audit sécurité, [FAIBLE]
            // fuite de PII) : ni email ni réglages/livreur, réservés à
            // `/api/me`.
            'utilisateur' => new UserPubliqueResource($beneficiaire),
            'niveau' => $niveau->value,
        ], 201);
    }

    public function retirerPartage(Request $request, Site $site, User $user): Response
    {
        $this->autoriserSite($request->user(), $site, 'partager');

        $acces = SiteAcces::where('site_id', $site->id)->where('user_id', $user->id)->first();

        // Un site doit toujours garder au moins un propriétaire (audit
        // sécurité, [FAIBLE] protection du dernier propriétaire).
        if ($acces?->niveau === NiveauAcces::Proprietaire && $this->estDernierProprietaire($site)) {
            abort(422, 'Impossible de retirer le dernier propriétaire du site.');
        }

        SiteAcces::where('site_id', $site->id)->where('user_id', $user->id)->delete();

        return response()->noContent();
    }

    /**
     * Désigne le livreur habituel **du site** (ADR 0009, maillons A et C ;
     * contrat API, §« Sites ») : réservé au propriétaire (`SitePolicy::
     * designerLivreurHabituel`). À ne pas confondre avec
     * `users.livreur_habituel_user_id` (préférence par défaut du compte,
     * `PATCH /api/me/reglages-alertes`) — sans colonne équivalente ici, un
     * seul livreur habituel actif par site (`livreur_habituel.site_id`
     * unique), remplacé par la nouvelle désignation le cas échéant.
     */
    public function designerLivreurHabituel(SiteLivreurHabituelRequest $request, Site $site): JsonResponse
    {
        $this->autoriserSite($request->user(), $site, 'designerLivreurHabituel');

        $livreur = User::where('telephone', $request->validated('telephone'))->firstOrFail();

        $livreurHabituel = LivreurHabituel::updateOrCreate(
            ['site_id' => $site->id],
            ['livreur_user_id' => $livreur->id, 'actif' => true],
        );

        return response()->json([
            'message' => 'Livreur habituel désigné.',
            'livreur' => new UserPubliqueResource($livreur),
            'actif' => $livreurHabituel->actif,
        ], 201);
    }

    /**
     * Retire la désignation du livreur habituel du site (ADR 0009) : réservé
     * au propriétaire, comme la désignation.
     */
    public function retirerLivreurHabituel(Request $request, Site $site): Response
    {
        $this->autoriserSite($request->user(), $site, 'designerLivreurHabituel');

        LivreurHabituel::where('site_id', $site->id)->delete();

        return response()->noContent();
    }

    /**
     * Le site n'a-t-il plus qu'un seul propriétaire ? Garde-fou avant un
     * retrait/rétrogradation de partage (audit sécurité, [FAIBLE] protection
     * du dernier propriétaire).
     */
    private function estDernierProprietaire(Site $site): bool
    {
        return SiteAcces::where('site_id', $site->id)
            ->where('niveau', NiveauAcces::Proprietaire->value)
            ->count() === 1;
    }

    /**
     * Hydrate un site des attributs calculés attendus par `SiteResource`
     * (`niveau_acces`, `bouteilles_count`, `a_alerte_active`) — pas des
     * colonnes du modèle, construits ici pour rester au plus près de
     * l'utilisateur courant.
     */
    private function hydrater(Site $site, User $user): Site
    {
        $bouteilleIds = $site->bouteilles()->pluck('id');

        $site->setAttribute('niveau_acces', $user->siteAcces()->where('site_id', $site->id)->value('niveau'));
        $site->setAttribute('bouteilles_count', $bouteilleIds->count());
        $site->setAttribute('a_alerte_active', Alerte::whereIn('bouteille_id', $bouteilleIds)
            ->whereIn('statut', [StatutAlerte::Emise->value, StatutAlerte::Vue->value])
            ->exists());

        return $site;
    }
}

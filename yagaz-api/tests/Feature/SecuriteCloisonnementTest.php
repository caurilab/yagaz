<?php

namespace Tests\Feature;

use App\Enums\NiveauAcces;
use App\Enums\RoleMembership;
use App\Models\Bouteille;
use App\Models\Commande;
use App\Models\FormatBouteille;
use App\Models\Membership;
use App\Models\Mesure;
use App\Models\NiveauCourant;
use App\Models\Organisation;
use App\Models\Plateau;
use App\Models\Site;
use App\Models\SiteAcces;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vérifie les corrections de l'audit sécurité (doc 07, §10) : un acteur PRO
 * ne voit jamais les données d'un foyer, le mass-assignment est bloqué sur
 * les tables d'autorisation, et la gestion d'une commande reste réservée au
 * membre direct de l'organisation cible.
 */
class SecuriteCloisonnementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Crée un foyer avec un site et un accès de niveau donné.
     *
     * @return array{0: User, 1: Site}
     */
    private function foyerAvecSite(NiveauAcces $niveau = NiveauAcces::Proprietaire): array
    {
        $user = User::factory()->create();
        $site = Site::factory()->create(['cree_par' => $user->id]);
        SiteAcces::forceCreate([
            'site_id' => $site->id,
            'user_id' => $user->id,
            'niveau' => $niveau->value,
        ]);

        return [$user, $site];
    }

    /**
     * Crée un utilisateur PRO membre actif d'une organisation avec le rôle donné.
     */
    private function proMembreDe(Organisation $organisation, RoleMembership $role): User
    {
        $user = User::factory()->create();
        Membership::forceCreate([
            'user_id' => $user->id,
            'organisation_id' => $organisation->id,
            'role' => $role->value,
            'actif' => true,
        ]);

        return $user;
    }

    // === [M1] Un PRO ne voit jamais les données d'un foyer ==============

    public function test_un_pro_ne_voit_jamais_une_mesure_ni_un_niveau_courant_de_foyer(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();

        $plateau = Plateau::factory()->actif()->create(['site_id' => $site->id]);
        $bouteille = Bouteille::factory()->create([
            'site_id' => $site->id,
            'plateau_id' => $plateau->id,
        ]);

        $mesure = Mesure::create([
            'plateau_id' => $plateau->id,
            'bouteille_id' => $bouteille->id,
            'mesure_at' => now(),
            'recu_at' => now(),
            'poids_g' => 15000,
            'gaz_g' => 12000,
            'seq' => 1,
        ]);

        $niveau = NiveauCourant::create([
            'bouteille_id' => $bouteille->id,
            'gaz_g' => 12000,
            'niveau_pct' => 96,
            'calcule_at' => now(),
        ]);

        $depot = Organisation::factory()->depot()->create();
        $mandataire = Organisation::factory()->mandataire()->create();
        $distributeur = Organisation::factory()->distributeur()->create();

        $gerant = $this->proMembreDe($depot, RoleMembership::GerantDepot);
        $mandataireUser = $this->proMembreDe($mandataire, RoleMembership::Mandataire);
        $distributeurUser = $this->proMembreDe($distributeur, RoleMembership::Distributeur);

        foreach ([$gerant, $mandataireUser, $distributeurUser] as $pro) {
            $this->assertFalse($pro->can('view', $mesure));
            $this->assertFalse($pro->can('view', $niveau));
        }

        // Le foyer propriétaire, lui, voit bien ses propres données.
        $this->assertTrue($foyer->can('view', $mesure));
        $this->assertTrue($foyer->can('view', $niveau));
    }

    // === [C1] Mass-assignment bloqué sur les tables d'autorisation =======
    //
    // `$fillable = []` combiné au `$guarded = ['*']` par défaut d'Eloquent
    // rend ces modèles « totalement gardés » : toute tentative de mass
    // assignment (constructeur, `fill()`, `create()`) lève une
    // MassAssignmentException plutôt que d'ignorer silencieusement les
    // colonnes — la protection est donc immédiate et explicite.

    public function test_le_mass_assignment_est_bloque_sur_membership(): void
    {
        $org = Organisation::factory()->depot()->create();
        $user = User::factory()->create();

        $this->expectException(MassAssignmentException::class);

        new Membership([
            'role' => RoleMembership::Distributeur->value,
            'organisation_id' => $org->id,
            'user_id' => $user->id,
            'actif' => true,
        ]);
    }

    public function test_le_mass_assignment_est_bloque_sur_site_acces(): void
    {
        [, $site] = $this->foyerAvecSite();
        $user = User::factory()->create();

        $this->expectException(MassAssignmentException::class);

        new SiteAcces([
            'site_id' => $site->id,
            'user_id' => $user->id,
            'niveau' => NiveauAcces::Proprietaire->value,
        ]);
    }

    // === [M2] Gestion d'une commande réservée au membre direct de la cible ===

    public function test_seul_le_membre_direct_du_depot_cible_peut_gerer_la_commande(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();

        $mandataire = Organisation::factory()->mandataire()->create();
        $depot = Organisation::factory()->depot()->create(['parent_id' => $mandataire->id]);

        $gerant = $this->proMembreDe($depot, RoleMembership::GerantDepot);
        $mandataireUser = $this->proMembreDe($mandataire, RoleMembership::Mandataire);

        $commande = Commande::factory()->create([
            'demandeur_user_id' => $foyer->id,
            'cible_org_id' => $depot->id,
            'site_id' => $site->id,
            'format_id' => FormatBouteille::factory()->create()->id,
        ]);

        // Le membre direct du dépôt cible peut la traiter.
        $this->assertTrue($gerant->can('update', $commande));

        // Le mandataire parent la voit (hiérarchie) mais ne peut pas la gérer.
        $this->assertTrue($mandataireUser->can('view', $commande));
        $this->assertFalse($mandataireUser->can('update', $commande));

        // Le foyer demandeur ne peut pas la gérer non plus.
        $this->assertFalse($foyer->can('update', $commande));
    }
}

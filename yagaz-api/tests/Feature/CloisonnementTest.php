<?php

namespace Tests\Feature;

use App\Enums\NiveauAcces;
use App\Enums\RoleMembership;
use App\Models\Bouteille;
use App\Models\Commande;
use App\Models\FormatBouteille;
use App\Models\Membership;
use App\Models\Organisation;
use App\Models\Plateau;
use App\Models\Site;
use App\Models\SiteAcces;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prouve que le cloisonnement du doc 07 (§10) tient : chaque acteur ne voit
 * que son périmètre. Ces tests sont le garde-fou sécurité de la Phase 1.
 */
class CloisonnementTest extends TestCase
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

    private function stockPour(Organisation $organisation): Stock
    {
        return Stock::forceCreate([
            'organisation_id' => $organisation->id,
            'format_id' => FormatBouteille::factory()->create()->id,
            'pleines' => 10,
            'vides' => 4,
            'seuil_plein_bas' => 3,
        ]);
    }

    // === Foyers / sites ===============================================

    public function test_un_foyer_ne_voit_pas_le_site_d_un_autre(): void
    {
        [$foyerA, $siteA] = $this->foyerAvecSite();
        [$foyerB, $siteB] = $this->foyerAvecSite();

        $this->assertTrue($foyerA->can('view', $siteA));
        $this->assertFalse($foyerA->can('view', $siteB));
        $this->assertFalse($foyerB->can('view', $siteA));
    }

    public function test_un_observateur_voit_le_site_mais_ne_peut_pas_le_gerer(): void
    {
        // Cas central : surveiller la bouteille d'un proche à distance.
        [$proprietaire, $site] = $this->foyerAvecSite();
        $observateur = User::factory()->create();
        SiteAcces::forceCreate([
            'site_id' => $site->id,
            'user_id' => $observateur->id,
            'niveau' => NiveauAcces::Observateur->value,
        ]);

        $this->assertTrue($observateur->can('view', $site));
        $this->assertFalse($observateur->can('update', $site));
        $this->assertTrue($proprietaire->can('update', $site));
    }

    public function test_la_bouteille_suit_l_acces_au_site(): void
    {
        [$foyerA, $siteA] = $this->foyerAvecSite();
        [$foyerB] = $this->foyerAvecSite();
        $bouteille = Bouteille::factory()->create(['site_id' => $siteA->id]);

        $this->assertTrue($foyerA->can('view', $bouteille));
        $this->assertFalse($foyerB->can('view', $bouteille));
    }

    public function test_le_plateau_suit_l_acces_au_site_et_un_plateau_non_pose_est_invisible(): void
    {
        [$foyerA, $siteA] = $this->foyerAvecSite();
        [$foyerB] = $this->foyerAvecSite();

        $plateauPose = Plateau::factory()->actif()->create(['site_id' => $siteA->id]);
        $plateauNonPose = Plateau::factory()->create(['site_id' => null]);

        $this->assertTrue($foyerA->can('view', $plateauPose));
        $this->assertFalse($foyerB->can('view', $plateauPose));
        $this->assertFalse($foyerA->can('view', $plateauNonPose));
    }

    // === Organisations / stocks =======================================

    public function test_un_depot_voit_son_stock_mais_pas_celui_d_un_autre_depot(): void
    {
        $depotX = Organisation::factory()->depot()->create();
        $depotY = Organisation::factory()->depot()->create();

        $gerant = User::factory()->create();
        Membership::forceCreate([
            'user_id' => $gerant->id,
            'organisation_id' => $depotX->id,
            'role' => RoleMembership::GerantDepot->value,
            'actif' => true,
        ]);

        $stockX = $this->stockPour($depotX);
        $stockY = $this->stockPour($depotY);

        $this->assertTrue($gerant->can('view', $stockX));
        $this->assertTrue($gerant->can('update', $stockX));
        $this->assertFalse($gerant->can('view', $stockY));
        $this->assertFalse($gerant->can('update', $stockY));
    }

    public function test_un_mandataire_voit_le_stock_de_ses_depots_sans_pouvoir_le_saisir(): void
    {
        $mandataire = Organisation::factory()->mandataire()->create();
        $depot = Organisation::factory()->depot()->create(['parent_id' => $mandataire->id]);

        $autreMandataire = Organisation::factory()->mandataire()->create();
        $autreDepot = Organisation::factory()->depot()->create(['parent_id' => $autreMandataire->id]);

        $mandataireUser = User::factory()->create();
        Membership::forceCreate([
            'user_id' => $mandataireUser->id,
            'organisation_id' => $mandataire->id,
            'role' => RoleMembership::Mandataire->value,
            'actif' => true,
        ]);

        $stockDepot = $this->stockPour($depot);
        $stockAutre = $this->stockPour($autreDepot);

        // Voit le stock de son dépôt (accès descendant)...
        $this->assertTrue($mandataireUser->can('view', $stockDepot));
        // ...mais ne le saisit pas (réservé au membre direct du dépôt).
        $this->assertFalse($mandataireUser->can('update', $stockDepot));
        // Ne voit pas le dépôt d'un autre mandataire.
        $this->assertFalse($mandataireUser->can('view', $stockAutre));
    }

    public function test_un_distributeur_voit_toute_sa_branche_mais_pas_une_autre(): void
    {
        $distributeur = Organisation::factory()->distributeur()->create();
        $mandataire = Organisation::factory()->mandataire()->create(['parent_id' => $distributeur->id]);
        $depot = Organisation::factory()->depot()->create(['parent_id' => $mandataire->id]);

        $autreDistributeur = Organisation::factory()->distributeur()->create();
        $autreDepot = Organisation::factory()->depot()->create(['parent_id' => $autreDistributeur->id]);

        $distUser = User::factory()->create();
        Membership::forceCreate([
            'user_id' => $distUser->id,
            'organisation_id' => $distributeur->id,
            'role' => RoleMembership::Distributeur->value,
            'actif' => true,
        ]);

        $this->assertTrue($distUser->can('view', $depot));
        $this->assertTrue($distUser->can('view', $mandataire));
        $this->assertFalse($distUser->can('view', $autreDepot));
    }

    public function test_un_membership_inactif_ne_donne_aucun_acces(): void
    {
        $depot = Organisation::factory()->depot()->create();
        $user = User::factory()->create();
        Membership::forceCreate([
            'user_id' => $user->id,
            'organisation_id' => $depot->id,
            'role' => RoleMembership::GerantDepot->value,
            'actif' => false,
        ]);

        $stock = $this->stockPour($depot);

        $this->assertFalse($user->can('view', $depot));
        $this->assertFalse($user->can('view', $stock));
    }

    public function test_un_livreur_ne_peut_pas_gerer_l_organisation(): void
    {
        $depot = Organisation::factory()->depot()->create();
        $livreur = User::factory()->create();
        Membership::forceCreate([
            'user_id' => $livreur->id,
            'organisation_id' => $depot->id,
            'role' => RoleMembership::Livreur->value,
            'actif' => true,
        ]);

        // Un livreur voit son organisation mais ne l'administre pas.
        $this->assertTrue($livreur->can('view', $depot));
        $this->assertFalse($livreur->can('update', $depot));
    }

    // === Commandes ====================================================

    public function test_visibilite_d_une_commande_de_foyer(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        [$autreFoyer] = $this->foyerAvecSite();

        $mandataire = Organisation::factory()->mandataire()->create();
        $depot = Organisation::factory()->depot()->create(['parent_id' => $mandataire->id]);

        $gerant = User::factory()->create();
        Membership::forceCreate([
            'user_id' => $gerant->id,
            'organisation_id' => $depot->id,
            'role' => RoleMembership::GerantDepot->value,
            'actif' => true,
        ]);

        $mandataireUser = User::factory()->create();
        Membership::forceCreate([
            'user_id' => $mandataireUser->id,
            'organisation_id' => $mandataire->id,
            'role' => RoleMembership::Mandataire->value,
            'actif' => true,
        ]);

        $commande = Commande::factory()->create([
            'demandeur_user_id' => $foyer->id,
            'cible_org_id' => $depot->id,
            'site_id' => $site->id,
            'format_id' => FormatBouteille::factory()->create()->id,
        ]);

        // Le foyer demandeur, le dépôt cible et son mandataire la voient.
        $this->assertTrue($foyer->can('view', $commande));
        $this->assertTrue($gerant->can('view', $commande));
        $this->assertTrue($mandataireUser->can('view', $commande));
        // Un autre foyer ne la voit pas.
        $this->assertFalse($autreFoyer->can('view', $commande));
    }

    // === Contraintes de données (le cloisonnement s'appuie dessus) =====

    public function test_une_seule_bouteille_active_par_site(): void
    {
        [, $site] = $this->foyerAvecSite();
        Bouteille::factory()->create(['site_id' => $site->id]); // active

        // Une bouteille de secours sur le même site est autorisée.
        $secours = Bouteille::factory()->secours()->create(['site_id' => $site->id]);
        $this->assertNotNull($secours->id);

        // Une SECONDE bouteille active sur le même site est refusée par l'index.
        $this->expectException(QueryException::class);
        Bouteille::factory()->create(['site_id' => $site->id]); // active -> viole l'unique partiel
    }
}

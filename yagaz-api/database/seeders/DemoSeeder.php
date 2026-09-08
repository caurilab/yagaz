<?php

namespace Database\Seeders;

use App\Enums\ModePaiement;
use App\Enums\NiveauAcces;
use App\Enums\OrigineCommande;
use App\Enums\RoleBouteille;
use App\Enums\RoleMembership;
use App\Enums\StatutCommande;
use App\Enums\StatutPaiement;
use App\Enums\TareSource;
use App\Models\Bouteille;
use App\Models\Commande;
use App\Models\FormatBouteille;
use App\Models\LivreurHabituel;
use App\Models\Membership;
use App\Models\NiveauCourant;
use App\Models\Organisation;
use App\Models\Plateau;
use App\Models\Site;
use App\Models\SiteAcces;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Jeu de données de démonstration illustrant tous les acteurs et le
 * déclenchement automatique (doc 07 + ADR 0009).
 *
 * Comptes de démo (mot de passe : "password" pour tous) :
 * - Foyer         : +221770000000
 * - Mandataire    : +221770000001
 * - Gérant dépôt  : +221770000002
 * - Livreur       : +221770000003
 * - Distributeur  : +221770000004
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // --- Hiérarchie d'organisations : distributeur -> mandataire -> 2 dépôts ---
        $distributeur = Organisation::factory()->distributeur()->create([
            'nom' => 'Distributeur National Demo',
            'zone' => 'Côte d\'Ivoire',
        ]);

        $mandataire = Organisation::factory()->mandataire()->create([
            'nom' => 'Mandataire Régional Demo',
            'parent_id' => $distributeur->id,
            'zone' => 'Abidjan',
        ]);

        $depot1 = Organisation::factory()->depot()->create([
            'nom' => 'Dépôt Demo Abobo',
            'parent_id' => $mandataire->id,
            'zone' => 'Abobo',
        ]);

        $depot2 = Organisation::factory()->depot()->create([
            'nom' => 'Dépôt Demo Koumassi',
            'parent_id' => $mandataire->id,
            'zone' => 'Koumassi',
        ]);

        // --- Comptes de démo par rôle (mot de passe factory : "password") ---
        $foyer = User::factory()->create([
            'name' => 'Foyer Demo',
            'email' => null,
            'telephone' => '+221770000000',
        ]);

        $this->membre('Mandataire Demo', '+221770000001', $mandataire, RoleMembership::Mandataire);
        $this->membre('Gérant Dépôt Demo', '+221770000002', $depot1, RoleMembership::GerantDepot);
        $livreur = $this->membre('Livreur Demo', '+221770000003', $depot1, RoleMembership::Livreur);
        $this->membre('Distributeur Demo', '+221770000004', $distributeur, RoleMembership::Distributeur);

        // --- Foyer : site + bouteille active sur plateau, en tension ---
        $site = Site::factory()->create([
            'nom' => 'Maison',
            'zone' => 'Abobo',
            'cree_par' => $foyer->id,
        ]);

        SiteAcces::query()->forceCreate([
            'site_id' => $site->id,
            'user_id' => $foyer->id,
            'niveau' => NiveauAcces::Proprietaire,
        ]);

        $formatB12 = FormatBouteille::query()->firstOrCreate(
            ['code' => 'B12', 'marque' => 'Total'],
            ['tare_nominale_g' => 13000, 'contenance_gaz_g' => 12500]
        );

        $plateau = Plateau::factory()->actif()->create(['site_id' => $site->id]);

        $bouteille = Bouteille::factory()->create([
            'site_id' => $site->id,
            'format_id' => $formatB12->id,
            'plateau_id' => $plateau->id,
            'role_bouteille' => RoleBouteille::Active,
            'tare_g' => $formatB12->tare_nominale_g,
            'tare_source' => TareSource::Nominale,
            'seuil_bas_pct' => 15,
        ]);

        // Dernier niveau connu : sous le seuil (en tension) -> autonomie ~10 h.
        NiveauCourant::query()->updateOrCreate(
            ['bouteille_id' => $bouteille->id],
            [
                'gaz_g' => 1500,
                'niveau_pct' => 12,
                'autonomie_min' => 600,
                'debit_g_par_h' => 150,
                'calcule_at' => now(),
            ]
        );

        // Le foyer a désigné le livreur comme livreur habituel du site
        // (active les maillons A et C du déclenchement automatique).
        LivreurHabituel::query()->forceCreate([
            'site_id' => $site->id,
            'livreur_user_id' => $livreur->id,
            'actif' => true,
        ]);

        // --- Stocks des dépôts (dont un format en tension pour la démo réappro) ---
        $formatB6 = FormatBouteille::query()->firstOrCreate(
            ['code' => 'B6', 'marque' => 'Total'],
            ['tare_nominale_g' => 7000, 'contenance_gaz_g' => 6000]
        );

        $this->stock($depot1, $formatB12, pleines: 4, vides: 9, seuil: 6);   // en tension
        $this->stock($depot1, $formatB6, pleines: 20, vides: 3, seuil: 5);
        $this->stock($depot2, $formatB12, pleines: 15, vides: 2, seuil: 6);

        // --- Une commande foyer -> dépôt, confirmée (visible foyer + dépôt, payable) ---
        Commande::query()->forceCreate([
            'origine' => OrigineCommande::Foyer,
            'demandeur_user_id' => $foyer->id,
            'cible_org_id' => $depot1->id,
            'site_id' => $site->id,
            'format_id' => $formatB12->id,
            'quantite' => 1,
            'statut' => StatutCommande::Confirmee,
            'mode_paiement' => ModePaiement::ALaLivraison,
            'statut_paiement' => StatutPaiement::EnAttente,
            'commission_g' => 50,
        ]);
    }

    private function membre(string $nom, string $telephone, Organisation $org, RoleMembership $role): User
    {
        $user = User::factory()->create([
            'name' => $nom,
            'email' => null,
            'telephone' => $telephone,
        ]);

        Membership::query()->forceCreate([
            'user_id' => $user->id,
            'organisation_id' => $org->id,
            'role' => $role,
            'actif' => true,
        ]);

        return $user;
    }

    private function stock(Organisation $org, FormatBouteille $format, int $pleines, int $vides, int $seuil): void
    {
        Stock::query()->forceCreate([
            'organisation_id' => $org->id,
            'format_id' => $format->id,
            'pleines' => $pleines,
            'vides' => $vides,
            'seuil_plein_bas' => $seuil,
        ]);
    }
}

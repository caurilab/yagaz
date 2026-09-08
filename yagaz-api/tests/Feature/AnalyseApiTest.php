<?php

namespace Tests\Feature;

use App\Enums\ModePaiement;
use App\Enums\NiveauAcces;
use App\Enums\RoleBouteille;
use App\Enums\StatutCommande;
use App\Enums\StatutPaiement;
use App\Models\Bouteille;
use App\Models\Commande;
use App\Models\FormatBouteille;
use App\Models\Marque;
use App\Models\Mesure;
use App\Models\NiveauCourant;
use App\Models\Organisation;
use App\Models\Paiement;
use App\Models\Plateau;
use App\Models\SessionCuisson;
use App\Models\Site;
use App\Models\SiteAcces;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * `GET /api/analyse` (doc 13, §2) : agrégats de consommation, dépense,
 * recharges, répartition, jours de cuisine et projection, cloisonnés au
 * périmètre foyer.
 */
class AnalyseApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_renvoie_la_structure_complete_avec_des_valeurs_coherentes(): void
    {
        $user = User::factory()->create();
        $site = Site::factory()->create(['cree_par' => $user->id]);

        SiteAcces::forceCreate([
            'site_id' => $site->id,
            'user_id' => $user->id,
            'niveau' => NiveauAcces::Proprietaire->value,
        ]);

        $marque = Marque::factory()->create(['couleur' => '#ff8800']);
        $format = FormatBouteille::factory()->create([
            'tare_nominale_g' => 13000,
            'contenance_gaz_g' => 12500,
            'marque_id' => $marque->id,
        ]);

        $plateau = Plateau::factory()->actif()->create(['site_id' => $site->id]);
        $bouteille = Bouteille::factory()->create([
            'site_id' => $site->id,
            'plateau_id' => $plateau->id,
            'format_id' => $format->id,
            'role_bouteille' => RoleBouteille::Active,
            'seuil_bas_pct' => 15,
            'tare_g' => 13000,
        ]);

        $debutMois = now()->startOfMonth()->addHours(2);

        // Consommation : 10000 -> 9000 (-1000, conso) -> 9500 (+500, recharge
        // exclue) -> 8000 (-1500, conso). Total attendu : 2500 g = 2,5 kg.
        Mesure::create([
            'plateau_id' => $plateau->id,
            'bouteille_id' => $bouteille->id,
            'mesure_at' => $debutMois,
            'recu_at' => $debutMois,
            'poids_g' => 23000,
            'gaz_g' => 10000,
            'seq' => 1,
        ]);
        Mesure::create([
            'plateau_id' => $plateau->id,
            'bouteille_id' => $bouteille->id,
            'mesure_at' => $debutMois->addHours(1),
            'recu_at' => $debutMois->addHours(1),
            'poids_g' => 22000,
            'gaz_g' => 9000,
            'seq' => 2,
        ]);
        Mesure::create([
            'plateau_id' => $plateau->id,
            'bouteille_id' => $bouteille->id,
            'mesure_at' => $debutMois->addHours(2),
            'recu_at' => $debutMois->addHours(2),
            'poids_g' => 22500,
            'gaz_g' => 9500,
            'seq' => 3,
        ]);
        Mesure::create([
            'plateau_id' => $plateau->id,
            'bouteille_id' => $bouteille->id,
            'mesure_at' => $debutMois->addHours(3),
            'recu_at' => $debutMois->addHours(3),
            'poids_g' => 21000,
            'gaz_g' => 8000,
            'seq' => 4,
        ]);

        NiveauCourant::create([
            'bouteille_id' => $bouteille->id,
            'gaz_g' => 8000,
            'niveau_pct' => 64,
            'autonomie_min' => 600, // 10h
            'debit_g_par_h' => 100.0,
            'calcule_at' => $debutMois->addHours(3),
        ]);

        $depot = Organisation::factory()->depot()->create();

        $commande = Commande::factory()->create([
            'site_id' => $site->id,
            'cible_org_id' => $depot->id,
            'format_id' => $format->id,
            'quantite' => 1,
            'statut' => StatutCommande::Livree,
            'mode_paiement' => ModePaiement::ALaLivraison,
        ]);
        Commande::whereKey($commande->id)->update(['updated_at' => $debutMois->addHours(1)]);

        $commandePayee = Commande::factory()->create([
            'site_id' => $site->id,
            'cible_org_id' => $depot->id,
            'format_id' => $format->id,
            'quantite' => 1,
            'statut' => StatutCommande::Livree,
            'mode_paiement' => ModePaiement::MobileMoney,
        ]);
        Commande::whereKey($commandePayee->id)->update(['updated_at' => $debutMois->addHours(2)]);

        $paiement = Paiement::factory()->create([
            'commande_id' => $commandePayee->id,
            'statut' => StatutPaiement::Regle,
            'montant' => 6500,
        ]);
        Paiement::whereKey($paiement->id)->update(['updated_at' => $debutMois->addHours(2)]);

        SessionCuisson::create([
            'site_id' => $site->id,
            'debut_at' => $debutMois->addMinutes(10),
            'fin_at' => $debutMois->addMinutes(40),
            'temp_max_c' => 55.0,
        ]);

        Sanctum::actingAs($user);
        $reponse = $this->getJson('/api/analyse?site_uuid='.$site->uuid.'&periode=mois');

        $reponse->assertOk();
        $reponse->assertJsonStructure([
            'data' => [
                'periode', 'debut', 'fin',
                'consommation_kg', 'consommation_tendance_pct',
                'depense_fcfa', 'depense_tendance_pct',
                'recharges' => ['nombre', 'cout_moyen_fcfa', 'frequence_jours'],
                'repartition' => ['par_bouteille', 'par_site'],
                'jours_cuisine' => ['nombre', 'serie_journaliere'],
                'autonomie_moyenne_h',
                'projection_prochaine_recharge_jours',
                'serie_consommation',
            ],
        ]);

        $donnees = $reponse->json('data');

        $this->assertEqualsWithDelta(2.5, $donnees['consommation_kg'], 0.01);
        // Dépense : 6500 (paiement réglé) + 6500 (commande livrée a_la_livraison, tarif estimé).
        $this->assertSame(13000, $donnees['depense_fcfa']);
        $this->assertSame(2, $donnees['recharges']['nombre']);
        $this->assertSame(6500, $donnees['recharges']['cout_moyen_fcfa']);
        $this->assertSame(1, $donnees['jours_cuisine']['nombre']);
        $this->assertCount(1, $donnees['jours_cuisine']['serie_journaliere']);
        $this->assertEqualsWithDelta(10.0, $donnees['autonomie_moyenne_h'], 0.01);
        // seuil bas = 12500 * 15% = 1875 g ; delta = 8000 - 1875 = 6125 g ;
        // heures = 6125 / 100 = 61,25h ; jours = 61,25 / 24 ≈ 2,6.
        $this->assertEqualsWithDelta(2.6, $donnees['projection_prochaine_recharge_jours'], 0.05);

        $this->assertCount(1, $donnees['repartition']['par_bouteille']);
        $this->assertSame('#ff8800', $donnees['repartition']['par_bouteille'][0]['couleur']);
        $this->assertCount(1, $donnees['repartition']['par_site']);
    }

    public function test_un_foyer_sans_site_recoit_une_structure_vide(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);
        $reponse = $this->getJson('/api/analyse');

        $reponse->assertOk();
        $donnees = $reponse->json('data');

        $this->assertEquals(0, $donnees['consommation_kg']);
        $this->assertSame(0, $donnees['depense_fcfa']);
        $this->assertSame(0, $donnees['recharges']['nombre']);
        $this->assertSame(0, $donnees['jours_cuisine']['nombre']);
        $this->assertNull($donnees['autonomie_moyenne_h']);
        $this->assertNull($donnees['projection_prochaine_recharge_jours']);
    }

    public function test_site_uuid_hors_perimetre_renvoie_404(): void
    {
        $proprietaire = User::factory()->create();
        $site = Site::factory()->create(['cree_par' => $proprietaire->id]);
        SiteAcces::forceCreate([
            'site_id' => $site->id,
            'user_id' => $proprietaire->id,
            'niveau' => NiveauAcces::Proprietaire->value,
        ]);

        $autreUser = User::factory()->create();

        Sanctum::actingAs($autreUser);
        $this->getJson('/api/analyse?site_uuid='.$site->uuid)->assertNotFound();
    }
}

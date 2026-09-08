<?php

namespace Tests\Feature;

use App\Enums\NiveauAcces;
use App\Enums\StatutCommande;
use App\Models\Commande;
use App\Models\Site;
use App\Models\SiteAcces;
use App\Models\User;
use App\Services\Payment\SimulateurPaiement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Reçu de paiement d'une commande (`GET /api/commandes/{uuid}/recu`) : reçu
 * du dernier paiement `regle` (Mobile Money réglé) ou reçu « à la livraison »
 * cohérent quand la commande n'est pas (encore) payée, cloisonnement.
 */
class CommandeRecuApiTest extends TestCase
{
    use RefreshDatabase;

    private const string SECRET_WEBHOOK = 'secret-test-webhook-recu';

    protected function setUp(): void
    {
        parent::setUp();

        config(['paiement.secret_webhook' => self::SECRET_WEBHOOK]);
    }

    /**
     * @return array{0: User, 1: Site}
     */
    private function foyerAvecSite(): array
    {
        $user = User::factory()->create();
        $site = Site::factory()->create(['cree_par' => $user->id, 'nom' => 'Résidence Test']);
        SiteAcces::forceCreate([
            'site_id' => $site->id,
            'user_id' => $user->id,
            'niveau' => NiveauAcces::Proprietaire->value,
        ]);

        return [$user, $site];
    }

    private function signer(array $payload): string
    {
        return SimulateurPaiement::signer($payload, self::SECRET_WEBHOOK);
    }

    public function test_le_recu_d_une_commande_reglee_par_mobile_money_expose_la_reference_et_le_montant(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        $commande = Commande::factory()->create([
            'demandeur_user_id' => $foyer->id,
            'site_id' => $site->id,
            'statut' => StatutCommande::Confirmee,
            'quantite' => 2,
        ]);

        Sanctum::actingAs($foyer);
        $init = $this->postJson("/api/commandes/{$commande->uuid}/paiement");
        $reference = $init->json('data.paiement.reference');
        $montant = $init->json('data.paiement.montant');

        $payload = ['reference' => $reference, 'statut' => 'regle', 'montant' => $montant, 'devise' => 'XOF'];
        $this->postJson('/api/paiements/webhook/simulateur', $payload, [
            'X-Paiement-Signature' => $this->signer($payload),
        ])->assertOk();

        $recu = $this->getJson("/api/commandes/{$commande->uuid}/recu");

        $recu->assertOk();
        $recu->assertJsonPath('data.reference', $reference);
        $recu->assertJsonPath('data.montant', $montant);
        $recu->assertJsonPath('data.devise', 'XOF');
        $recu->assertJsonPath('data.statut_paiement', 'regle');
        $recu->assertJsonPath('data.mode_paiement', 'mobile_money');
        $recu->assertJsonPath('data.commande.uuid', $commande->uuid);
        $recu->assertJsonPath('data.commande.quantite', 2);
        $recu->assertJsonPath('data.site.nom', 'Résidence Test');
        $this->assertNotNull($recu->json('data.date'));
    }

    public function test_le_recu_d_une_commande_non_payee_est_coherent_a_la_livraison(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        $commande = Commande::factory()->create([
            'demandeur_user_id' => $foyer->id,
            'site_id' => $site->id,
            'statut' => StatutCommande::Confirmee,
            'quantite' => 3,
        ]);

        Sanctum::actingAs($foyer);
        $recu = $this->getJson("/api/commandes/{$commande->uuid}/recu");

        $recu->assertOk();
        $recu->assertJsonPath('data.reference', null);
        $recu->assertJsonPath('data.statut_paiement', 'en_attente');
        $recu->assertJsonPath('data.mode_paiement', 'a_la_livraison');
        $recu->assertJsonPath('data.montant', 3 * 6_500);
        $recu->assertJsonPath('data.commande.quantite', 3);
    }

    public function test_un_autre_foyer_ne_peut_pas_voir_le_recu(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        [$autreFoyer] = $this->foyerAvecSite();
        $commande = Commande::factory()->create([
            'demandeur_user_id' => $foyer->id,
            'site_id' => $site->id,
            'statut' => StatutCommande::Confirmee,
        ]);

        Sanctum::actingAs($autreFoyer);
        $this->getJson("/api/commandes/{$commande->uuid}/recu")->assertNotFound();
    }
}

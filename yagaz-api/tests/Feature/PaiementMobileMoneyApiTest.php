<?php

namespace Tests\Feature;

use App\Enums\NiveauAcces;
use App\Enums\StatutCommande;
use App\Enums\StatutPaiement;
use App\Models\Commande;
use App\Models\Paiement;
use App\Models\Site;
use App\Models\SiteAcces;
use App\Models\User;
use App\Services\Payment\SimulateurPaiement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Paiement Mobile Money (ADR 0010, v2 brique 1) : initiation par le foyer
 * propriétaire, cloisonnement, webhook signé/idempotent/vérifié en montant,
 * statut jamais modifiable par le client, réconciliation.
 */
class PaiementMobileMoneyApiTest extends TestCase
{
    use RefreshDatabase;

    private const string SECRET_WEBHOOK = 'secret-test-webhook';

    protected function setUp(): void
    {
        parent::setUp();

        // Secret de signature du webhook (ADR 0010 §Sécurité) : jamais en dur
        // dans le code applicatif, ici injecté pour le test comme il le
        // serait via l'environnement.
        config(['paiement.secret_webhook' => self::SECRET_WEBHOOK]);
    }

    /**
     * @return array{0: User, 1: Site}
     */
    private function foyerAvecSite(): array
    {
        $user = User::factory()->create();
        $site = Site::factory()->create(['cree_par' => $user->id]);
        SiteAcces::forceCreate([
            'site_id' => $site->id,
            'user_id' => $user->id,
            'niveau' => NiveauAcces::Proprietaire->value,
        ]);

        return [$user, $site];
    }

    private function commandeConfirmee(Site $site, User $foyer, int $quantite = 2): Commande
    {
        return Commande::factory()->create([
            'demandeur_user_id' => $foyer->id,
            'site_id' => $site->id,
            'statut' => StatutCommande::Confirmee,
            'quantite' => $quantite,
        ]);
    }

    /**
     * Signe un payload comme le ferait le provider (utilitaire de test).
     *
     * @param  array<string, mixed>  $payload
     */
    private function signer(array $payload): string
    {
        return SimulateurPaiement::signer($payload, self::SECRET_WEBHOOK);
    }

    // === Initiation =======================================================

    public function test_le_foyer_proprietaire_initie_un_paiement_pour_une_commande_confirmee(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        $commande = $this->commandeConfirmee($site, $foyer, quantite: 2);

        Sanctum::actingAs($foyer);
        $reponse = $this->postJson("/api/commandes/{$commande->uuid}/paiement");

        $reponse->assertCreated();
        $reponse->assertJsonPath('data.paiement.statut', 'initie');
        $reponse->assertJsonPath('data.paiement.montant', 13000);
        $reponse->assertJsonPath('data.paiement.devise', 'XOF');
        $this->assertNotEmpty($reponse->json('data.paiement.reference'));
        $this->assertNotEmpty($reponse->json('data.intention'));

        $this->assertDatabaseHas('paiements', [
            'commande_id' => $commande->id,
            'provider' => 'simulateur',
            'statut' => 'initie',
            'montant' => 13000,
        ]);
        $this->assertDatabaseHas('commandes', [
            'uuid' => $commande->uuid,
            'mode_paiement' => 'mobile_money',
            'statut_paiement' => 'initie',
        ]);
    }

    public function test_un_autre_foyer_ne_peut_pas_payer_cette_commande(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        [$autreFoyer] = $this->foyerAvecSite();
        $commande = $this->commandeConfirmee($site, $foyer);

        Sanctum::actingAs($autreFoyer);
        $this->postJson("/api/commandes/{$commande->uuid}/paiement")->assertNotFound();
        $this->getJson("/api/commandes/{$commande->uuid}/paiement")->assertNotFound();

        $this->assertDatabaseCount('paiements', 0);
    }

    public function test_le_statut_de_paiement_n_est_pas_modifiable_via_un_endpoint_client(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        $commande = $this->commandeConfirmee($site, $foyer);

        Sanctum::actingAs($foyer);
        // Tentative de forcer le statut directement dans le corps de la
        // requête : le contrôleur ne lit aucun champ du corps pour initier
        // (ADR 0005) — le statut initial reste `initie`, jamais `regle`.
        $reponse = $this->postJson("/api/commandes/{$commande->uuid}/paiement", [
            'statut' => 'regle',
            'montant' => 1,
        ]);

        $reponse->assertCreated();
        $reponse->assertJsonPath('data.paiement.statut', 'initie');
        $reponse->assertJsonPath('data.paiement.montant', 13000);
        $this->assertDatabaseHas('paiements', ['commande_id' => $commande->id, 'statut' => 'initie']);
    }

    public function test_l_initiation_est_refusee_pour_une_commande_qui_n_est_pas_confirmee(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        $commande = Commande::factory()->create([
            'demandeur_user_id' => $foyer->id,
            'site_id' => $site->id,
            'statut' => StatutCommande::Livree,
        ]);

        Sanctum::actingAs($foyer);
        $this->postJson("/api/commandes/{$commande->uuid}/paiement")->assertStatus(422);

        $this->assertDatabaseCount('paiements', 0);
    }

    public function test_la_reinitiation_est_refusee_pour_une_commande_deja_reglee(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        $commande = $this->commandeConfirmee($site, $foyer);

        Sanctum::actingAs($foyer);
        $init = $this->postJson("/api/commandes/{$commande->uuid}/paiement");
        $reference = $init->json('data.paiement.reference');
        $montant = $init->json('data.paiement.montant');

        $payload = ['reference' => $reference, 'statut' => 'regle', 'montant' => $montant, 'devise' => 'XOF'];
        $this->postJson('/api/paiements/webhook/simulateur', $payload, [
            'X-Paiement-Signature' => $this->signer($payload),
        ])->assertOk();

        // Ré-initiation sur une commande déjà réglée : refusée, le paiement
        // `regle` existant reste intact (bug corrigé : double-facturation).
        $this->postJson("/api/commandes/{$commande->uuid}/paiement")->assertStatus(422);

        $this->assertDatabaseCount('paiements', 1);
        $this->assertDatabaseHas('paiements', ['reference' => $reference, 'statut' => 'regle', 'montant' => $montant]);
    }

    public function test_la_reinitiation_avec_une_intention_en_cours_reutilise_le_meme_paiement(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        $commande = $this->commandeConfirmee($site, $foyer);

        Sanctum::actingAs($foyer);
        $premier = $this->postJson("/api/commandes/{$commande->uuid}/paiement");
        $premier->assertCreated();
        $referenceInitiale = $premier->json('data.paiement.reference');

        // Deuxième appel pendant que l'intention est toujours `initie` : pas
        // de second paiement créé, la même intention est renvoyée.
        $second = $this->postJson("/api/commandes/{$commande->uuid}/paiement");
        $second->assertCreated();
        $second->assertJsonPath('data.paiement.reference', $referenceInitiale);
        $second->assertJsonPath('data.paiement.statut', 'initie');

        $this->assertDatabaseCount('paiements', 1);
    }

    // === Webhook ===========================================================

    public function test_webhook_avec_signature_valide_regle_la_commande_et_le_paiement(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        $commande = $this->commandeConfirmee($site, $foyer);

        Sanctum::actingAs($foyer);
        $init = $this->postJson("/api/commandes/{$commande->uuid}/paiement");
        $reference = $init->json('data.paiement.reference');
        $montant = $init->json('data.paiement.montant');

        $payload = ['reference' => $reference, 'statut' => 'regle', 'montant' => $montant, 'devise' => 'XOF'];

        $webhook = $this->postJson('/api/paiements/webhook/simulateur', $payload, [
            'X-Paiement-Signature' => $this->signer($payload),
        ]);

        $webhook->assertOk();
        $this->assertDatabaseHas('paiements', ['reference' => $reference, 'statut' => 'regle']);
        $this->assertDatabaseHas('commandes', ['uuid' => $commande->uuid, 'statut_paiement' => 'regle']);
    }

    public function test_webhook_avec_signature_invalide_est_rejete_sans_changement(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        $commande = $this->commandeConfirmee($site, $foyer);

        Sanctum::actingAs($foyer);
        $init = $this->postJson("/api/commandes/{$commande->uuid}/paiement");
        $reference = $init->json('data.paiement.reference');
        $montant = $init->json('data.paiement.montant');

        $payload = ['reference' => $reference, 'statut' => 'regle', 'montant' => $montant, 'devise' => 'XOF'];

        $webhook = $this->postJson('/api/paiements/webhook/simulateur', $payload, [
            'X-Paiement-Signature' => 'signature-invalide',
        ]);

        $webhook->assertStatus(400);
        $this->assertDatabaseHas('paiements', ['reference' => $reference, 'statut' => 'initie']);
        $this->assertDatabaseHas('commandes', ['uuid' => $commande->uuid, 'statut_paiement' => 'initie']);
    }

    public function test_webhook_sans_en_tete_de_signature_est_rejete(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        $commande = $this->commandeConfirmee($site, $foyer);

        Sanctum::actingAs($foyer);
        $init = $this->postJson("/api/commandes/{$commande->uuid}/paiement");
        $reference = $init->json('data.paiement.reference');
        $montant = $init->json('data.paiement.montant');

        $payload = ['reference' => $reference, 'statut' => 'regle', 'montant' => $montant, 'devise' => 'XOF'];

        // Aucun en-tête `X-Paiement-Signature` : signature nulle, rejetée.
        $webhook = $this->postJson('/api/paiements/webhook/simulateur', $payload);

        $webhook->assertStatus(400);
        $this->assertDatabaseHas('paiements', ['reference' => $reference, 'statut' => 'initie']);
    }

    public function test_webhook_avec_une_devise_incoherente_est_rejete(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        $commande = $this->commandeConfirmee($site, $foyer);

        Sanctum::actingAs($foyer);
        $init = $this->postJson("/api/commandes/{$commande->uuid}/paiement");
        $reference = $init->json('data.paiement.reference');
        $montant = $init->json('data.paiement.montant');

        // Devise différente de celle enregistrée à l'initiation (XOF).
        $payload = ['reference' => $reference, 'statut' => 'regle', 'montant' => $montant, 'devise' => 'EUR'];

        $webhook = $this->postJson('/api/paiements/webhook/simulateur', $payload, [
            'X-Paiement-Signature' => $this->signer($payload),
        ]);

        $webhook->assertStatus(400);
        $this->assertDatabaseHas('paiements', ['reference' => $reference, 'statut' => 'initie', 'devise' => 'XOF']);
        $this->assertDatabaseHas('commandes', ['uuid' => $commande->uuid, 'statut_paiement' => 'initie']);
    }

    public function test_webhook_avec_le_statut_echoue_fait_echouer_la_commande_et_le_paiement(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        $commande = $this->commandeConfirmee($site, $foyer);

        Sanctum::actingAs($foyer);
        $init = $this->postJson("/api/commandes/{$commande->uuid}/paiement");
        $reference = $init->json('data.paiement.reference');
        $montant = $init->json('data.paiement.montant');

        $payload = ['reference' => $reference, 'statut' => 'echoue', 'montant' => $montant, 'devise' => 'XOF'];

        $webhook = $this->postJson('/api/paiements/webhook/simulateur', $payload, [
            'X-Paiement-Signature' => $this->signer($payload),
        ]);

        $webhook->assertOk();
        $this->assertDatabaseHas('paiements', ['reference' => $reference, 'statut' => 'echoue']);
        $this->assertDatabaseHas('commandes', ['uuid' => $commande->uuid, 'statut_paiement' => 'echoue']);
    }

    public function test_webhook_sans_reference_est_rejete_proprement(): void
    {
        $payload = ['statut' => 'regle', 'montant' => 1000, 'devise' => 'XOF'];

        $this->postJson('/api/paiements/webhook/simulateur', $payload, [
            'X-Paiement-Signature' => $this->signer($payload),
        ])->assertStatus(400);

        $this->assertDatabaseCount('paiements', 0);
    }

    public function test_rejeu_du_meme_webhook_est_idempotent(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        $commande = $this->commandeConfirmee($site, $foyer);

        Sanctum::actingAs($foyer);
        $init = $this->postJson("/api/commandes/{$commande->uuid}/paiement");
        $reference = $init->json('data.paiement.reference');
        $montant = $init->json('data.paiement.montant');

        $payload = ['reference' => $reference, 'statut' => 'regle', 'montant' => $montant, 'devise' => 'XOF'];
        $signature = $this->signer($payload);

        $this->postJson('/api/paiements/webhook/simulateur', $payload, ['X-Paiement-Signature' => $signature])
            ->assertOk();

        // Rejeu de la même notification : idempotent, pas de double-crédit.
        $this->postJson('/api/paiements/webhook/simulateur', $payload, ['X-Paiement-Signature' => $signature])
            ->assertOk();

        $this->assertDatabaseCount('paiements', 1);
        $this->assertDatabaseHas('paiements', ['reference' => $reference, 'statut' => 'regle']);
    }

    public function test_webhook_avec_un_montant_incoherent_est_rejete(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        $commande = $this->commandeConfirmee($site, $foyer);

        Sanctum::actingAs($foyer);
        $init = $this->postJson("/api/commandes/{$commande->uuid}/paiement");
        $reference = $init->json('data.paiement.reference');

        // Montant différent de celui enregistré à l'initiation (13000).
        $payload = ['reference' => $reference, 'statut' => 'regle', 'montant' => 1, 'devise' => 'XOF'];

        $webhook = $this->postJson('/api/paiements/webhook/simulateur', $payload, [
            'X-Paiement-Signature' => $this->signer($payload),
        ]);

        $webhook->assertStatus(400);
        $this->assertDatabaseHas('paiements', ['reference' => $reference, 'statut' => 'initie', 'montant' => 13000]);
        $this->assertDatabaseHas('commandes', ['uuid' => $commande->uuid, 'statut_paiement' => 'initie']);
    }

    public function test_webhook_pour_une_reference_inconnue_est_rejete(): void
    {
        $payload = ['reference' => 'SIM-INCONNUE', 'statut' => 'regle', 'montant' => 1000, 'devise' => 'XOF'];

        $this->postJson('/api/paiements/webhook/simulateur', $payload, [
            'X-Paiement-Signature' => $this->signer($payload),
        ])->assertStatus(400);
    }

    // === Réconciliation ====================================================

    public function test_la_reconciliation_met_a_jour_un_paiement_initie_selon_le_provider(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        $commande = $this->commandeConfirmee($site, $foyer);

        $paiement = Paiement::factory()->create([
            'commande_id' => $commande->id,
            'reference' => 'SIM-RECONCILIATION-TEST',
            'statut' => StatutPaiement::Initie,
        ]);
        // Assez ancien pour être éligible à la réconciliation active.
        $paiement->forceFill(['created_at' => now()->subMinutes(30)])->save();

        // Le provider (simulé) sait que cette référence a en réalité réussi.
        SimulateurPaiement::memoriserStatut('SIM-RECONCILIATION-TEST', 'regle');

        $this->artisan('paiements:reconcilier')->assertExitCode(0);

        $this->assertDatabaseHas('paiements', ['id' => $paiement->id, 'statut' => 'regle']);
        $this->assertDatabaseHas('commandes', ['id' => $commande->id, 'statut_paiement' => 'regle']);
    }

    public function test_la_reconciliation_ignore_un_paiement_initie_trop_recent(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        $commande = $this->commandeConfirmee($site, $foyer);

        $paiement = Paiement::factory()->create([
            'commande_id' => $commande->id,
            'reference' => 'SIM-TROP-RECENT',
            'statut' => StatutPaiement::Initie,
        ]);

        SimulateurPaiement::memoriserStatut('SIM-TROP-RECENT', 'regle');

        $this->artisan('paiements:reconcilier')->assertExitCode(0);

        // Trop récent : pas encore réconcilié, reste `initie`.
        $this->assertDatabaseHas('paiements', ['id' => $paiement->id, 'statut' => 'initie']);
    }
}

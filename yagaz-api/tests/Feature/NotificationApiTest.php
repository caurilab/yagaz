<?php

namespace Tests\Feature;

use App\Enums\NiveauAcces;
use App\Enums\RoleMembership;
use App\Models\Commande;
use App\Models\FormatBouteille;
use App\Models\Membership;
use App\Models\Organisation;
use App\Models\Site;
use App\Models\SiteAcces;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Notifications (contrat API doc 11, §3) : une alerte adressée. Un
 * événement (commande préparée, proposition de livraison, changement de
 * statut) crée une notification pour le bon foyer ; `GET /api/notifications`
 * ne renvoie que les siennes.
 */
class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

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

    /**
     * @return array{0: Organisation, 1: FormatBouteille, 2: Stock}
     */
    private function depotAvecStock(int $pleines = 10): array
    {
        $depot = Organisation::factory()->depot()->create();
        $format = FormatBouteille::factory()->create();
        $stock = Stock::forceCreate([
            'organisation_id' => $depot->id,
            'format_id' => $format->id,
            'pleines' => $pleines,
            'vides' => 0,
            'seuil_plein_bas' => 2,
        ]);

        return [$depot, $format, $stock];
    }

    public function test_la_preparation_d_une_commande_notifie_le_bon_foyer(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        [$depot, $format] = $this->depotAvecStock();

        $gerant = User::factory()->create();
        Membership::forceCreate([
            'user_id' => $gerant->id,
            'organisation_id' => $depot->id,
            'role' => RoleMembership::GerantDepot->value,
            'actif' => true,
        ]);

        Sanctum::actingAs($foyer);
        $uuid = $this->postJson('/api/commandes', [
            'site_uuid' => $site->uuid,
            'format_id' => $format->id,
            'quantite' => 1,
            'depot_uuid' => $depot->uuid,
        ])->json('data.uuid');

        Sanctum::actingAs($gerant);
        $this->patchJson("/api/commandes/{$uuid}/preparer")->assertOk();

        Sanctum::actingAs($foyer);
        $notifications = $this->getJson('/api/notifications');

        $notifications->assertOk();
        $notifications->assertJsonCount(1, 'data');
        $notifications->assertJsonPath('data.0.type', 'commande_preparee');
        $notifications->assertJsonPath('data.0.commande_uuid', $uuid);
        $notifications->assertJsonPath('data.0.statut', 'emise');

        $this->assertDatabaseHas('alertes', [
            'destinataire_user_id' => $foyer->id,
            'type' => 'commande_preparee',
        ]);
    }

    public function test_get_notifications_ne_renvoie_que_les_siennes(): void
    {
        [$foyerA, $siteA] = $this->foyerAvecSite();
        [$foyerB, $siteB] = $this->foyerAvecSite();
        [$depot, $format] = $this->depotAvecStock(pleines: 20);

        $gerant = User::factory()->create();
        Membership::forceCreate([
            'user_id' => $gerant->id,
            'organisation_id' => $depot->id,
            'role' => RoleMembership::GerantDepot->value,
            'actif' => true,
        ]);

        foreach ([[$foyerA, $siteA], [$foyerB, $siteB]] as [$foyer, $site]) {
            Sanctum::actingAs($foyer);
            $uuid = $this->postJson('/api/commandes', [
                'site_uuid' => $site->uuid,
                'format_id' => $format->id,
                'quantite' => 1,
                'depot_uuid' => $depot->uuid,
            ])->json('data.uuid');

            Sanctum::actingAs($gerant);
            $this->patchJson("/api/commandes/{$uuid}/preparer")->assertOk();
        }

        Sanctum::actingAs($foyerA);
        $reponse = $this->getJson('/api/notifications');
        $reponse->assertOk();
        $reponse->assertJsonCount(1, 'data');

        Sanctum::actingAs($foyerB);
        $reponse = $this->getJson('/api/notifications');
        $reponse->assertOk();
        $reponse->assertJsonCount(1, 'data');
    }

    public function test_une_proposition_de_livraison_notifie_le_foyer_destinataire(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        [$depot, $format] = $this->depotAvecStock();

        $gerant = User::factory()->create();
        Membership::forceCreate([
            'user_id' => $gerant->id,
            'organisation_id' => $depot->id,
            'role' => RoleMembership::GerantDepot->value,
            'actif' => true,
        ]);

        // Rendre le site « client » du dépôt (heuristique v1).
        Commande::factory()->create(['site_id' => $site->id, 'cible_org_id' => $depot->id, 'format_id' => $format->id]);

        Sanctum::actingAs($gerant);
        $this->postJson("/api/depots/{$depot->uuid}/propositions", [
            'site_uuid' => $site->uuid,
            'format_id' => $format->id,
            'quantite' => 1,
        ])->assertCreated();

        Sanctum::actingAs($foyer);
        $reponse = $this->getJson('/api/notifications');
        $reponse->assertOk();

        $types = collect($reponse->json('data'))->pluck('type');
        $this->assertTrue($types->contains('proposition_livraison'));
    }

    public function test_patch_notification_marque_vue(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        [$depot, $format] = $this->depotAvecStock();

        $gerant = User::factory()->create();
        Membership::forceCreate([
            'user_id' => $gerant->id,
            'organisation_id' => $depot->id,
            'role' => RoleMembership::GerantDepot->value,
            'actif' => true,
        ]);

        Sanctum::actingAs($foyer);
        $uuid = $this->postJson('/api/commandes', [
            'site_uuid' => $site->uuid,
            'format_id' => $format->id,
            'quantite' => 1,
            'depot_uuid' => $depot->uuid,
        ])->json('data.uuid');

        Sanctum::actingAs($gerant);
        $this->patchJson("/api/commandes/{$uuid}/preparer")->assertOk();

        Sanctum::actingAs($foyer);
        $notificationId = $this->getJson('/api/notifications')->json('data.0.id');

        $reponse = $this->patchJson("/api/notifications/{$notificationId}", ['statut' => 'vue']);
        $reponse->assertOk();
        $reponse->assertJsonPath('data.statut', 'vue');
    }

    public function test_un_autre_foyer_ne_peut_pas_marquer_vue_la_notification_d_un_autre(): void
    {
        [$foyerA, $siteA] = $this->foyerAvecSite();
        [$foyerB] = $this->foyerAvecSite();
        [$depot, $format] = $this->depotAvecStock();

        $gerant = User::factory()->create();
        Membership::forceCreate([
            'user_id' => $gerant->id,
            'organisation_id' => $depot->id,
            'role' => RoleMembership::GerantDepot->value,
            'actif' => true,
        ]);

        Sanctum::actingAs($foyerA);
        $uuid = $this->postJson('/api/commandes', [
            'site_uuid' => $siteA->uuid,
            'format_id' => $format->id,
            'quantite' => 1,
            'depot_uuid' => $depot->uuid,
        ])->json('data.uuid');

        Sanctum::actingAs($gerant);
        $this->patchJson("/api/commandes/{$uuid}/preparer")->assertOk();

        Sanctum::actingAs($foyerA);
        $notificationId = $this->getJson('/api/notifications')->json('data.0.id');

        Sanctum::actingAs($foyerB);
        $this->patchJson("/api/notifications/{$notificationId}", ['statut' => 'vue'])->assertNotFound();
    }
}

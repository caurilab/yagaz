<?php

namespace Tests\Feature;

use App\Enums\CanalAlerte;
use App\Enums\NiveauAcces;
use App\Enums\StatutAlerte;
use App\Enums\TypeAlerte;
use App\Models\Alerte;
use App\Models\Bouteille;
use App\Models\Site;
use App\Models\SiteAcces;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Alertes du périmètre foyer et préférences de notification (contrat API,
 * §« Alertes »).
 */
class AlerteApiTest extends TestCase
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

    public function test_les_alertes_sont_filtrees_par_perimetre_et_par_statut(): void
    {
        [$foyerA, $siteA] = $this->foyerAvecSite();
        $bouteilleA = Bouteille::factory()->create(['site_id' => $siteA->id]);
        $emise = Alerte::create([
            'bouteille_id' => $bouteilleA->id,
            'type' => TypeAlerte::SeuilBas,
            'statut' => StatutAlerte::Emise,
            'canal' => CanalAlerte::Push,
        ]);
        $resolue = Alerte::create([
            'bouteille_id' => $bouteilleA->id,
            'type' => TypeAlerte::SeuilBas,
            'statut' => StatutAlerte::Resolue,
            'canal' => CanalAlerte::Push,
        ]);

        [, $siteB] = $this->foyerAvecSite();
        $bouteilleB = Bouteille::factory()->create(['site_id' => $siteB->id]);
        Alerte::create([
            'bouteille_id' => $bouteilleB->id,
            'type' => TypeAlerte::SeuilBas,
            'statut' => StatutAlerte::Emise,
            'canal' => CanalAlerte::Push,
        ]);

        Sanctum::actingAs($foyerA);

        // Sans filtre : seulement les alertes de son propre périmètre.
        $reponse = $this->getJson('/api/alertes');
        $reponse->assertOk();
        $reponse->assertJsonCount(2, 'data');

        // Avec filtre statut=emise : seulement l'alerte émise de son périmètre.
        $reponse = $this->getJson('/api/alertes?statut=emise');
        $reponse->assertOk();
        $reponse->assertJsonCount(1, 'data');
        $reponse->assertJsonPath('data.0.id', $emise->id);
        $this->assertNotSame($resolue->id, $reponse->json('data.0.id'));
    }

    public function test_patch_alerte_change_son_statut(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        $bouteille = Bouteille::factory()->create(['site_id' => $site->id]);
        $alerte = Alerte::create([
            'bouteille_id' => $bouteille->id,
            'type' => TypeAlerte::SeuilBas,
            'statut' => StatutAlerte::Emise,
            'canal' => CanalAlerte::Push,
        ]);

        Sanctum::actingAs($foyer);

        $reponse = $this->patchJson("/api/alertes/{$alerte->id}", ['statut' => 'vue']);

        $reponse->assertOk();
        $reponse->assertJsonPath('data.statut', 'vue');
        $this->assertSame(StatutAlerte::Vue, $alerte->fresh()->statut);
    }

    public function test_un_foyer_ne_peut_pas_modifier_l_alerte_d_un_autre(): void
    {
        [, $siteA] = $this->foyerAvecSite();
        $bouteilleA = Bouteille::factory()->create(['site_id' => $siteA->id]);
        $alerte = Alerte::create([
            'bouteille_id' => $bouteilleA->id,
            'type' => TypeAlerte::SeuilBas,
            'statut' => StatutAlerte::Emise,
            'canal' => CanalAlerte::Push,
        ]);

        [$foyerB] = $this->foyerAvecSite();
        Sanctum::actingAs($foyerB);

        $this->patchJson("/api/alertes/{$alerte->id}", ['statut' => 'vue'])->assertNotFound();
    }

    public function test_patch_reglages_alertes_stocke_les_canaux_et_le_livreur_habituel(): void
    {
        $foyer = User::factory()->create();
        $livreur = User::factory()->create(['telephone' => '+221770009999']);

        Sanctum::actingAs($foyer);

        $reponse = $this->patchJson('/api/me/reglages-alertes', [
            'canaux' => ['push', 'sms'],
            'livreur_habituel' => '+221770009999',
        ]);

        $reponse->assertOk();
        $reponse->assertJsonPath('user.reglages_alertes.canaux', ['push', 'sms']);
        $reponse->assertJsonPath('user.reglages_alertes.livreur_habituel', '+221770009999');

        $foyer->refresh();
        $this->assertSame(['push', 'sms'], $foyer->canaux_alerte);
        $this->assertSame($livreur->id, $foyer->livreur_habituel_user_id);
    }
}

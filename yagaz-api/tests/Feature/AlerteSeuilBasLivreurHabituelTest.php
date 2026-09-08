<?php

namespace Tests\Feature;

use App\Enums\NiveauAcces;
use App\Enums\TypeAlerte;
use App\Models\Alerte;
use App\Models\Bouteille;
use App\Models\FormatBouteille;
use App\Models\LivreurHabituel;
use App\Models\Plateau;
use App\Models\Site;
use App\Models\SiteAcces;
use App\Models\User;
use App\Services\Mesure\TraitementMesure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * ADR 0009, maillon A — seuil bas → notification du livreur habituel, en
 * plus du foyer, avec l'information MINIMALE de l'ADR 0008. Test
 * d'étanchéité dédié (ADR 0008) : la notification du livreur tiers ne
 * laisse jamais fuiter niveau/autonomie/historique/références foyer/contact.
 */
class AlerteSeuilBasLivreurHabituelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Site}
     */
    private function foyerAvecSite(string $zone = 'Plateau'): array
    {
        $user = User::factory()->create();
        $site = Site::factory()->create(['cree_par' => $user->id, 'zone' => $zone]);
        SiteAcces::forceCreate([
            'site_id' => $site->id,
            'user_id' => $user->id,
            'niveau' => NiveauAcces::Proprietaire->value,
        ]);

        return [$user, $site];
    }

    /**
     * @return array{0: Bouteille, 1: Plateau}
     */
    private function bouteilleActiveSurSite(Site $site, string $formatCode = 'B12'): array
    {
        $plateau = Plateau::factory()->actif()->create(['site_id' => $site->id]);
        $format = FormatBouteille::factory()->create([
            'code' => $formatCode,
            'tare_nominale_g' => 13000,
            'contenance_gaz_g' => 12500,
        ]);
        $bouteille = Bouteille::factory()->create([
            'site_id' => $site->id,
            'plateau_id' => $plateau->id,
            'format_id' => $format->id,
            'tare_g' => 13000,
            'tare_fiable' => true,
            'seuil_bas_pct' => 15,
        ]);

        return [$bouteille, $plateau];
    }

    /**
     * @param  array<string, mixed>  $champs
     * @return array<string, mixed>
     */
    private function message(Plateau $plateau, int $poidsG, int $seq, array $champs = []): array
    {
        return array_merge([
            'uid' => $plateau->uid,
            'v' => 1,
            'ts' => now()->timestamp,
            'poids_g' => $poidsG,
            'seq' => $seq,
        ], $champs);
    }

    /**
     * Fait franchir le seuil bas de la bouteille posée sur ce plateau.
     */
    private function franchirLeSeuilBas(Plateau $plateau): void
    {
        $service = new TraitementMesure;
        $seq = 1;

        $service->traiter($this->message($plateau, 19250, $seq++)); // 50 %, au-dessus du seuil.

        // Descente soutenue vers un niveau bas (poids proche de la tare) :
        // le franchissement doit se produire dans la série.
        for ($i = 0; $i < 6; $i++) {
            $service->traiter($this->message($plateau, 13625, $seq++));
        }
    }

    public function test_le_franchissement_du_seuil_notifie_aussi_le_livreur_habituel_actif(): void
    {
        [$foyer, $site] = $this->foyerAvecSite(zone: 'Plateau');
        [$bouteille, $plateau] = $this->bouteilleActiveSurSite($site, formatCode: 'B12');

        $livreur = User::factory()->create();
        LivreurHabituel::create([
            'site_id' => $site->id,
            'livreur_user_id' => $livreur->id,
            'actif' => true,
        ]);

        $this->franchirLeSeuilBas($plateau);

        // Le foyer reste notifié (comportement existant préservé).
        Sanctum::actingAs($foyer);
        $notificationsFoyer = $this->getJson('/api/notifications');
        $notificationsFoyer->assertOk();
        $notificationsFoyer->assertJsonCount(1, 'data');
        $notificationsFoyer->assertJsonPath('data.0.type', 'seuil_bas');
        $notificationsFoyer->assertJsonPath('data.0.bouteille_uuid', $bouteille->uuid);

        // Le livreur habituel reçoit AUSSI une notification.
        Sanctum::actingAs($livreur);
        $notificationsLivreur = $this->getJson('/api/notifications');
        $notificationsLivreur->assertOk();
        $notificationsLivreur->assertJsonCount(1, 'data');
        $notificationsLivreur->assertJsonPath('data.0.type', 'seuil_bas');

        $donnees = $notificationsLivreur->json('data.0');
        $this->assertSame([
            'site_nom' => $site->nom,
            'zone' => 'Plateau',
            'format_code' => 'B12',
        ], $donnees['contexte']);
    }

    /**
     * Test d'étanchéité dédié (ADR 0008) : la notification du livreur tiers
     * ne porte NI niveau, NI autonomie, NI `site_id`/`bouteille_id`, NI
     * contact, NI les autres bouteilles/sites du foyer — seulement
     * `contexte` {site_nom, zone, format_code}.
     */
    public function test_etancheite_la_notification_du_livreur_ne_fuite_aucune_donnee_foyer(): void
    {
        [, $site] = $this->foyerAvecSite(zone: 'Almadies');
        [, $plateau] = $this->bouteilleActiveSurSite($site, formatCode: 'B6');

        $livreur = User::factory()->create();
        LivreurHabituel::create([
            'site_id' => $site->id,
            'livreur_user_id' => $livreur->id,
            'actif' => true,
        ]);

        // Un autre site/foyer quelconque, sans rapport, pour vérifier qu'il
        // n'apparaît jamais dans la notification du livreur.
        [, $autreSite] = $this->foyerAvecSite(zone: 'Ngor');
        $this->bouteilleActiveSurSite($autreSite, formatCode: 'B24');

        $this->franchirLeSeuilBas($plateau);

        Sanctum::actingAs($livreur);
        $reponse = $this->getJson('/api/notifications');
        $reponse->assertOk();
        $reponse->assertJsonCount(1, 'data');

        $donnees = $reponse->json('data.0');

        // Aucune référence foyer.
        $this->assertNull($donnees['site_uuid']);
        $this->assertNull($donnees['bouteille_uuid']);
        $this->assertNull($donnees['organisation_uuid']);
        $this->assertNull($donnees['commande_uuid']);

        // Seule la projection minimale attendue est présente dans `contexte`.
        $this->assertEqualsCanonicalizing(['site_nom', 'zone', 'format_code'], array_keys($donnees['contexte']));
        $this->assertSame($site->nom, $donnees['contexte']['site_nom']);
        $this->assertSame('Almadies', $donnees['contexte']['zone']);
        $this->assertSame('B6', $donnees['contexte']['format_code']);

        // Ni niveau, ni autonomie, ni historique, ni contact, nulle part
        // dans la réponse — et rien qui identifie l'autre site/format.
        $texteBrut = (string) $reponse->getContent();
        $this->assertStringNotContainsString('niveau_pct', $texteBrut);
        $this->assertStringNotContainsString('autonomie', $texteBrut);
        $this->assertStringNotContainsString('Ngor', $texteBrut);
        $this->assertStringNotContainsString('B24', $texteBrut);

        // La ligne d'alerte en base ne porte pas non plus ces références.
        $this->assertDatabaseHas('alertes', [
            'destinataire_user_id' => $livreur->id,
            'type' => 'seuil_bas',
            'site_id' => null,
            'bouteille_id' => null,
        ]);
    }

    public function test_sans_livreur_habituel_seul_le_foyer_est_notifie(): void
    {
        [$foyer, $site] = $this->foyerAvecSite();
        [, $plateau] = $this->bouteilleActiveSurSite($site);

        $this->franchirLeSeuilBas($plateau);

        Sanctum::actingAs($foyer);
        $this->getJson('/api/notifications')->assertOk()->assertJsonCount(1, 'data');

        // Aucune alerte adressée à un tiers.
        $this->assertSame(1, Alerte::where('type', TypeAlerte::SeuilBas->value)->count());
    }

    public function test_un_livreur_habituel_inactif_n_est_pas_notifie(): void
    {
        [, $site] = $this->foyerAvecSite();
        [, $plateau] = $this->bouteilleActiveSurSite($site);

        $livreur = User::factory()->create();
        LivreurHabituel::create([
            'site_id' => $site->id,
            'livreur_user_id' => $livreur->id,
            'actif' => false,
        ]);

        $this->franchirLeSeuilBas($plateau);

        Sanctum::actingAs($livreur);
        $this->getJson('/api/notifications')->assertOk()->assertJsonCount(0, 'data');
    }
}

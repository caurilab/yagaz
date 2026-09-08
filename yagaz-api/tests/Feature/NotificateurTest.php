<?php

namespace Tests\Feature;

use App\Enums\NiveauAcces;
use App\Enums\TypeAlerte;
use App\Models\Site;
use App\Models\SiteAcces;
use App\Models\User;
use App\Services\Notification\Notificateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Invariant anti-fuite du notificateur (audit sécurité, [INFO] garde
 * notificateur, doc 04 §8, doc 07 §9) : une alerte ne porte la référence
 * `site`/`bouteille` que si le destinataire a effectivement accès à ce site.
 */
class NotificateurTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_destinataire_sans_acces_au_site_ne_recoit_pas_les_references_foyer(): void
    {
        $proprietaire = User::factory()->create();
        $site = Site::factory()->create(['cree_par' => $proprietaire->id]);
        SiteAcces::forceCreate([
            'site_id' => $site->id,
            'user_id' => $proprietaire->id,
            'niveau' => NiveauAcces::Proprietaire->value,
        ]);

        // Tiers sans aucun accès au site.
        $tiers = User::factory()->create();

        $alerte = app(Notificateur::class)->notifier(
            $tiers,
            TypeAlerte::SeuilBas,
            [],
            site: $site,
        );

        $this->assertNull($alerte->site_id);
        $this->assertSame($tiers->id, $alerte->destinataire_user_id);
    }

    public function test_l_appelant_legitime_proprietaire_du_site_porte_bien_la_reference_site(): void
    {
        $proprietaire = User::factory()->create();
        $site = Site::factory()->create(['cree_par' => $proprietaire->id]);
        SiteAcces::forceCreate([
            'site_id' => $site->id,
            'user_id' => $proprietaire->id,
            'niveau' => NiveauAcces::Proprietaire->value,
        ]);

        $alerte = app(Notificateur::class)->notifier(
            $proprietaire,
            TypeAlerte::SeuilBas,
            [],
            site: $site,
        );

        $this->assertSame($site->id, $alerte->site_id);
        $this->assertSame($proprietaire->id, $alerte->destinataire_user_id);
    }
}

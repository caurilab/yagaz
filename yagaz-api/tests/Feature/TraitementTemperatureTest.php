<?php

namespace Tests\Feature;

use App\Enums\NiveauAcces;
use App\Enums\StatutAlerte;
use App\Enums\StatutIngestionTemperature;
use App\Enums\StatutPlateau;
use App\Enums\TypeAlerte;
use App\Models\Alerte;
use App\Models\Plateau;
use App\Models\SessionCuisson;
use App\Models\Site;
use App\Models\SiteAcces;
use App\Models\Temperature;
use App\Models\User;
use App\Services\Temperature\TraitementTemperature;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pipeline d'ingestion d'une température de cuisine (ADR 0011) : résolution/
 * auth, validation, dédup, rangement, détection de cuisson, alerte sécurité.
 */
class TraitementTemperatureTest extends TestCase
{
    use RefreshDatabase;

    private TraitementTemperature $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TraitementTemperature;
    }

    /**
     * Plateau actif posé sur un site fraîchement créé, avec un propriétaire
     * identifiable (pour les tests d'alerte adressée au foyer).
     *
     * @return array{0: Plateau, 1: Site}
     */
    private function plateauActifAvecProprietaire(): array
    {
        $proprietaire = User::factory()->create();
        $site = Site::factory()->create(['cree_par' => $proprietaire->id]);

        SiteAcces::forceCreate([
            'site_id' => $site->id,
            'user_id' => $proprietaire->id,
            'niveau' => NiveauAcces::Proprietaire->value,
        ]);

        $plateau = Plateau::factory()->actif()->create(['site_id' => $site->id]);

        return [$plateau, $site];
    }

    /**
     * @param  array<string, mixed>  $champs
     * @return array<string, mixed>
     */
    private function message(Plateau $plateau, float $tempC, int $seq, array $champs = []): array
    {
        return array_merge([
            'uid' => $plateau->uid,
            'v' => 1,
            'ts' => now()->timestamp,
            'temp_c' => $tempC,
            'seq' => $seq,
        ], $champs);
    }

    public function test_uid_inconnu_est_rejete_et_rien_n_est_range(): void
    {
        $resultat = $this->service->traiter([
            'uid' => 'PLT-INCONNU',
            'v' => 1,
            'ts' => now()->timestamp,
            'temp_c' => 45,
            'seq' => 1,
        ]);

        $this->assertSame(StatutIngestionTemperature::Rejetee, $resultat->statut);
        $this->assertSame(0, Temperature::count());
    }

    public function test_un_plateau_non_actif_est_rejete(): void
    {
        $plateau = Plateau::factory()->create(['statut' => StatutPlateau::Provisionne]);

        $resultat = $this->service->traiter($this->message($plateau, 45, 1));

        $this->assertSame(StatutIngestionTemperature::Rejetee, $resultat->statut);
        $this->assertSame(0, Temperature::count());
    }

    public function test_un_plateau_sans_site_est_rejete(): void
    {
        $plateau = Plateau::factory()->create(['statut' => StatutPlateau::Actif, 'site_id' => null]);

        $resultat = $this->service->traiter($this->message($plateau, 45, 1));

        $this->assertSame(StatutIngestionTemperature::Rejetee, $resultat->statut);
        $this->assertSame(0, Temperature::count());
    }

    public function test_un_doublon_de_seq_est_ignore_une_seule_ligne_est_rangee(): void
    {
        [$plateau] = $this->plateauActifAvecProprietaire();

        $premier = $this->service->traiter($this->message($plateau, 25, 1));
        $doublon = $this->service->traiter($this->message($plateau, 25, 1));

        $this->assertSame(StatutIngestionTemperature::Rangee, $premier->statut);
        $this->assertSame(StatutIngestionTemperature::Doublon, $doublon->statut);
        $this->assertSame(1, Temperature::where('plateau_id', $plateau->id)->count());
    }

    public function test_une_temp_c_aberrante_est_rejetee(): void
    {
        [$plateau] = $this->plateauActifAvecProprietaire();

        $resultat = $this->service->traiter($this->message($plateau, 500, 1));

        $this->assertSame(StatutIngestionTemperature::Rejetee, $resultat->statut);
        $this->assertSame(0, Temperature::count());
    }

    public function test_un_seq_gigantesque_est_rejete(): void
    {
        [$plateau] = $this->plateauActifAvecProprietaire();

        $resultat = $this->service->traiter($this->message($plateau, 45, 1, [
            'seq' => 1_000_000_000_000_000_000,
        ]));

        $this->assertSame(StatutIngestionTemperature::Rejetee, $resultat->statut);
        $this->assertSame(0, Temperature::count());
    }

    public function test_une_temperature_sous_le_seuil_de_cuisson_ne_declenche_rien(): void
    {
        [$plateau] = $this->plateauActifAvecProprietaire();

        $resultat = $this->service->traiter($this->message($plateau, 22, 1));

        $this->assertSame(StatutIngestionTemperature::Rangee, $resultat->statut);
        $this->assertFalse($resultat->cuissonEnCours);
        $this->assertSame(0, SessionCuisson::count());
    }

    public function test_une_temperature_au_dessus_du_seuil_de_cuisson_ouvre_une_session(): void
    {
        [$plateau, $site] = $this->plateauActifAvecProprietaire();

        $seuil = (float) config('mesure.seuil_cuisson_c');
        $resultat = $this->service->traiter($this->message($plateau, $seuil + 5, 1));

        $this->assertSame(StatutIngestionTemperature::Rangee, $resultat->statut);
        $this->assertTrue($resultat->cuissonEnCours);
        $this->assertNotNull($resultat->sessionCuisson);
        $this->assertNull($resultat->sessionCuisson->fin_at);
        $this->assertSame($site->id, $resultat->sessionCuisson->site_id);
    }

    public function test_une_session_de_cuisson_ouverte_est_maintenue_et_le_max_mis_a_jour(): void
    {
        [$plateau] = $this->plateauActifAvecProprietaire();

        $seuil = (float) config('mesure.seuil_cuisson_c');
        $this->service->traiter($this->message($plateau, $seuil + 2, 1));
        $resultat = $this->service->traiter($this->message($plateau, $seuil + 10, 2));

        $this->assertTrue($resultat->cuissonEnCours);
        $this->assertSame(1, SessionCuisson::count());
        $this->assertEqualsWithDelta($seuil + 10, $resultat->sessionCuisson->temp_max_c, 0.01);
    }

    public function test_une_temperature_qui_redescend_sous_le_seuil_ferme_la_session(): void
    {
        [$plateau] = $this->plateauActifAvecProprietaire();

        $seuil = (float) config('mesure.seuil_cuisson_c');
        $this->service->traiter($this->message($plateau, $seuil + 5, 1));
        $resultat = $this->service->traiter($this->message($plateau, $seuil - 5, 2));

        $this->assertFalse($resultat->cuissonEnCours);
        $this->assertNotNull($resultat->sessionCuisson);
        $this->assertNotNull($resultat->sessionCuisson->fin_at);
        $this->assertSame(1, SessionCuisson::count());
    }

    public function test_le_franchissement_du_seuil_danger_cree_une_seule_alerte_adressee_au_foyer(): void
    {
        [$plateau, $site] = $this->plateauActifAvecProprietaire();

        $seuilDanger = (float) config('mesure.seuil_danger_c');

        $seq = 1;
        for ($i = 0; $i < 5; $i++) {
            $this->service->traiter($this->message($plateau, $seuilDanger + 5, $seq++));
        }

        $this->assertSame(1, Alerte::where('type', TypeAlerte::TemperatureElevee)->count());

        $alerte = Alerte::where('type', TypeAlerte::TemperatureElevee)->first();
        $this->assertSame($site->id, $alerte->site_id);
        $this->assertNotNull($alerte->destinataire_user_id);
    }

    public function test_une_alerte_danger_resolue_par_la_redescente_permet_une_nouvelle_alerte_a_la_remontee(): void
    {
        [$plateau] = $this->plateauActifAvecProprietaire();

        $seuilDanger = (float) config('mesure.seuil_danger_c');

        $this->service->traiter($this->message($plateau, $seuilDanger + 5, 1));
        $this->service->traiter($this->message($plateau, $seuilDanger - 10, 2)); // redescend : résout.

        $alerte = Alerte::where('type', TypeAlerte::TemperatureElevee)->first();
        $this->assertSame(StatutAlerte::Resolue, $alerte->statut);

        $this->service->traiter($this->message($plateau, $seuilDanger + 5, 3)); // remonte : nouvelle alerte.

        $this->assertSame(2, Alerte::where('type', TypeAlerte::TemperatureElevee)->count());
    }

    public function test_un_ts_aberrant_est_rejete(): void
    {
        [$plateau] = $this->plateauActifAvecProprietaire();

        $resultat = $this->service->traiter($this->message($plateau, 25, 1, [
            'ts' => CarbonImmutable::parse('2000-01-01', 'UTC')->timestamp,
        ]));

        $this->assertSame(StatutIngestionTemperature::Rejetee, $resultat->statut);
        $this->assertSame(0, Temperature::count());
    }
}

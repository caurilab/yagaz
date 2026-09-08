<?php

namespace Tests\Feature;

use App\Enums\RoleBouteille;
use App\Enums\StatutIngestionMesure;
use App\Enums\StatutPlateau;
use App\Enums\TypeAlerte;
use App\Models\Alerte;
use App\Models\Bouteille;
use App\Models\FormatBouteille;
use App\Models\Mesure;
use App\Models\NiveauCourant;
use App\Models\Plateau;
use App\Models\Site;
use App\Services\Mesure\TraitementMesure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pipeline d'ingestion d'une mesure (doc 08 §3-8) : résolution/auth,
 * validation, dédup, rangement, calibrage de tare, niveau/autonomie, alerte.
 */
class TraitementMesureTest extends TestCase
{
    use RefreshDatabase;

    private TraitementMesure $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TraitementMesure;
    }

    /**
     * Plateau actif posé sur un site fraîchement créé.
     */
    private function plateauActif(): Plateau
    {
        $site = Site::factory()->create();

        return Plateau::factory()->actif()->create(['site_id' => $site->id]);
    }

    /**
     * Bouteille posée sur ce plateau (nominale ~13000g de tare, ~12500g de
     * contenance — format par défaut de la factory).
     *
     * @param  array<string, mixed>  $attributs
     */
    private function bouteillePosee(Plateau $plateau, array $attributs = []): Bouteille
    {
        return Bouteille::factory()->create(array_merge([
            'site_id' => $plateau->site_id,
            'plateau_id' => $plateau->id,
            'format_id' => FormatBouteille::factory()->create([
                'tare_nominale_g' => 13000,
                'contenance_gaz_g' => 12500,
            ]),
        ], $attributs));
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

    public function test_uid_inconnu_est_rejete_et_rien_n_est_range(): void
    {
        $resultat = $this->service->traiter([
            'uid' => 'PLT-INCONNU',
            'v' => 1,
            'ts' => now()->timestamp,
            'poids_g' => 15000,
            'seq' => 1,
        ]);

        $this->assertSame(StatutIngestionMesure::Rejetee, $resultat->statut);
        $this->assertSame(0, Mesure::count());
    }

    public function test_un_plateau_non_actif_est_rejete(): void
    {
        $plateau = Plateau::factory()->create(['statut' => StatutPlateau::Provisionne]);

        $resultat = $this->service->traiter($this->message($plateau, 15000, 1));

        $this->assertSame(StatutIngestionMesure::Rejetee, $resultat->statut);
        $this->assertSame(0, Mesure::count());
    }

    public function test_un_doublon_de_seq_est_ignore_une_seule_ligne_est_rangee(): void
    {
        $plateau = $this->plateauActif();

        $premier = $this->service->traiter($this->message($plateau, 15000, 1));
        $doublon = $this->service->traiter($this->message($plateau, 15000, 1));

        $this->assertSame(StatutIngestionMesure::Rangee, $premier->statut);
        $this->assertSame(StatutIngestionMesure::Doublon, $doublon->statut);
        $this->assertSame(1, Mesure::where('plateau_id', $plateau->id)->count());
    }

    public function test_un_seq_en_regression_est_traite_comme_un_doublon(): void
    {
        $plateau = $this->plateauActif();

        $this->service->traiter($this->message($plateau, 15000, 5));
        $regression = $this->service->traiter($this->message($plateau, 15000, 3));

        $this->assertSame(StatutIngestionMesure::Doublon, $regression->statut);
        $this->assertSame(1, Mesure::where('plateau_id', $plateau->id)->count());
    }

    public function test_un_poids_negatif_est_rejete(): void
    {
        $plateau = $this->plateauActif();

        $resultat = $this->service->traiter($this->message($plateau, -5, 1));

        $this->assertSame(StatutIngestionMesure::Rejetee, $resultat->statut);
        $this->assertSame(0, Mesure::count());
    }

    public function test_un_poids_superieur_a_60kg_est_rejete(): void
    {
        $plateau = $this->plateauActif();

        $resultat = $this->service->traiter($this->message($plateau, 70000, 1));

        $this->assertSame(StatutIngestionMesure::Rejetee, $resultat->statut);
        $this->assertSame(0, Mesure::count());
    }

    public function test_une_sequence_decroissante_fait_baisser_le_niveau_et_met_a_jour_niveaux_courants(): void
    {
        $plateau = $this->plateauActif();
        $bouteille = $this->bouteillePosee($plateau, [
            'tare_g' => 13000,
            'tare_fiable' => true,
        ]);

        // 19250g -> 6250g de gaz -> 50%.
        $this->service->traiter($this->message($plateau, 19250, 1));
        $niveauInitial = NiveauCourant::find($bouteille->id)->niveau_pct;

        // Descente marquée et soutenue vers un poids proche de la tare.
        foreach ([17000, 15000, 13800, 13500] as $i => $poids) {
            $this->service->traiter($this->message($plateau, $poids, $i + 2));
        }

        $niveauCourant = NiveauCourant::find($bouteille->id);

        $this->assertNotNull($niveauCourant);
        $this->assertLessThan($niveauInitial, $niveauCourant->niveau_pct);
        $this->assertSame($bouteille->id, $niveauCourant->bouteille_id);
    }

    public function test_le_calibrage_de_tare_converge_vers_le_plancher_observe_et_finit_fiable(): void
    {
        $plateau = $this->plateauActif();
        $bouteille = $this->bouteillePosee($plateau); // tare_g null, nominale 13000, marge 2000.

        $seq = 1;

        // Descente initiale (bouteille pleine -> quasi vide), puis un plancher
        // observé de façon répétée (12800g, sous la tare nominale 13000g).
        foreach ([20000, 16000, 13000] as $poids) {
            $this->service->traiter($this->message($plateau, $poids, $seq++));
        }

        for ($i = 0; $i < 15; $i++) {
            $this->service->traiter($this->message($plateau, 12800, $seq++));
        }

        $bouteille->refresh();

        $this->assertTrue($bouteille->tare_fiable);
        $this->assertEqualsWithDelta(12800, $bouteille->tare_g, 200);
    }

    public function test_le_franchissement_du_seuil_bas_sur_une_bouteille_active_cree_une_seule_alerte(): void
    {
        $plateau = $this->plateauActif();
        $this->bouteillePosee($plateau, [
            'tare_g' => 13000,
            'tare_fiable' => true,
            'seuil_bas_pct' => 15,
        ]);

        $seq = 1;
        $this->service->traiter($this->message($plateau, 19250, $seq++)); // 50 %, au-dessus du seuil.

        // Descente soutenue vers un niveau bas (poids proche de la tare) :
        // le franchissement doit se produire une seule fois dans la série.
        for ($i = 0; $i < 6; $i++) {
            $this->service->traiter($this->message($plateau, 13625, $seq++));
        }

        $this->assertSame(1, Alerte::where('type', TypeAlerte::SeuilBas)->count());
    }

    public function test_une_bouteille_de_secours_sous_le_seuil_ne_declenche_pas_d_alerte(): void
    {
        $plateau = $this->plateauActif();
        $this->bouteillePosee($plateau, [
            'tare_g' => 13000,
            'tare_fiable' => true,
            'seuil_bas_pct' => 15,
            'role_bouteille' => RoleBouteille::Secours,
        ]);

        $this->service->traiter($this->message($plateau, 13300, 1)); // ~2 %, largement sous le seuil.

        $this->assertSame(0, Alerte::where('type', TypeAlerte::SeuilBas)->count());
    }

    public function test_un_poids_sous_le_seuil_plateau_nu_ne_calcule_pas_de_niveau(): void
    {
        $plateau = $this->plateauActif();
        $bouteille = $this->bouteillePosee($plateau, ['tare_g' => 13000, 'tare_fiable' => true]);

        $resultat = $this->service->traiter($this->message($plateau, 500, 1));

        $this->assertSame(StatutIngestionMesure::Rangee, $resultat->statut);
        $this->assertNull(NiveauCourant::find($bouteille->id));
    }
}

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
use Carbon\CarbonImmutable;
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

    /**
     * Audit sécurité, correctif #2 : la dédup ne se base plus sur `max(seq)`
     * du plateau (sinon un plateau qui redémarre et repart à un `seq` bas se
     * retrouve avec toutes ses mesures rejetées en doublon). Un `seq` en
     * régression mais jamais vu auparavant pour ce plateau est donc accepté.
     */
    public function test_un_seq_en_regression_jamais_vu_est_accepte_pas_traite_comme_un_doublon(): void
    {
        $plateau = $this->plateauActif();

        $this->service->traiter($this->message($plateau, 15000, 5));
        $regression = $this->service->traiter($this->message($plateau, 15000, 3));

        $this->assertSame(StatutIngestionMesure::Rangee, $regression->statut);
        $this->assertSame(2, Mesure::where('plateau_id', $plateau->id)->count());
    }

    /**
     * Audit sécurité, correctif #2 (test obligatoire #2) : après une série
     * normale, un plateau qui redémarre repart avec un `seq` bas (nouveau
     * `mesure_at` postérieur) — cette mesure doit être rangée, pas rejetée
     * comme doublon.
     */
    public function test_un_redemarrage_de_plateau_avec_reset_de_seq_est_accepte(): void
    {
        $plateau = $this->plateauActif();

        foreach (range(100, 105) as $seq) {
            $this->service->traiter($this->message($plateau, 15000, $seq));
        }

        $apresRedemarrage = $this->service->traiter($this->message($plateau, 15000, 1, [
            'ts' => now()->addMinute()->timestamp,
        ]));

        $this->assertSame(StatutIngestionMesure::Rangee, $apresRedemarrage->statut);
        $this->assertSame(7, Mesure::where('plateau_id', $plateau->id)->count());
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
        $bouteille = $this->bouteillePosee($plateau); // tare_g null, nominale 13000, marge 1000.

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

    /**
     * Audit sécurité, correctif #1 (test obligatoire #1a — LE test
     * critique) : une bouteille active dont le poids brut approche la tare
     * nominale déclenche une alerte seuil_bas même si sa tare calibrée est
     * faussée basse, au point que le niveau_pct calculé avec cette tare
     * reste au-dessus du seuil. Le filet de sécurité absolu
     * (`securite_marge_plancher_g`) ne dépend jamais de la tare calibrée.
     */
    public function test_une_tare_faussee_basse_ne_masque_pas_une_bouteille_physiquement_quasi_vide(): void
    {
        $plateau = $this->plateauActif();
        $bouteille = $this->bouteillePosee($plateau, [
            'tare_g' => 10000, // Tare empoisonnée, bien en dessous de la vraie tare (13000).
            'tare_fiable' => true,
            'seuil_bas_pct' => 15,
        ]);

        // Poids physique quasi vide (à 100g de la tare nominale 13000g), mais
        // avec la tare faussée le niveau calculé reste artificiellement haut.
        $resultat = $this->service->traiter($this->message($plateau, 13100, 1));

        $niveauCourant = NiveauCourant::find($bouteille->id);

        $this->assertNotNull($niveauCourant);
        $this->assertGreaterThan(15, $niveauCourant->niveau_pct); // Le niveau calculé masque le vide réel.
        $this->assertSame(1, Alerte::where('type', TypeAlerte::SeuilBas)->count()); // Le filet déclenche quand même.
        $this->assertNotNull($resultat->alerte);
    }

    /**
     * Audit sécurité, correctif #1 (test obligatoire #1b) : une série de
     * poids stables sous la tare nominale ne fait jamais descendre la tare
     * en dessous de `nominale − marge_tare` (borne plausible respectée).
     */
    public function test_une_serie_de_poids_stables_sous_la_tare_nominale_ne_fait_pas_descendre_la_tare_sous_la_borne(): void
    {
        $plateau = $this->plateauActif();
        $bouteille = $this->bouteillePosee($plateau); // tare_g null, nominale 13000.

        $margeTare = (int) config('mesure.marge_tare');
        $borneBasse = 13000 - $margeTare;

        $seq = 1;

        // Établit un plancher légitime, pile à la borne basse plausible.
        for ($i = 0; $i < 6; $i++) {
            $this->service->traiter($this->message($plateau, $borneBasse, $seq++));
        }

        // Poids nettement sous la tare nominale, hors plage plausible :
        // ignorés, ne doivent jamais faire descendre la tare sous la borne.
        for ($i = 0; $i < 10; $i++) {
            $this->service->traiter($this->message($plateau, $borneBasse - 3000, $seq++));
        }

        $bouteille->refresh();

        $this->assertNotNull($bouteille->tare_g);
        $this->assertGreaterThanOrEqual($borneBasse, $bouteille->tare_g);
    }

    /**
     * Audit sécurité, correctif #4 (test obligatoire #4a) : un `seq` hors
     * plage plausible est rejeté plutôt que rangé.
     */
    public function test_un_seq_gigantesque_est_rejete(): void
    {
        $plateau = $this->plateauActif();

        $resultat = $this->service->traiter($this->message($plateau, 15000, 1, [
            'seq' => 1_000_000_000_000_000_000,
        ]));

        $this->assertSame(StatutIngestionMesure::Rejetee, $resultat->statut);
        $this->assertSame(0, Mesure::count());
    }

    /**
     * Audit sécurité, correctif #4 (test obligatoire #4b) : un `ts` aberrant
     * (trop ancien ou trop futur) est rejeté — distinct du simple bornage de
     * dérive d'horloge qui recale les petites dérives sans rejeter.
     */
    public function test_un_ts_de_l_an_2000_est_rejete(): void
    {
        $plateau = $this->plateauActif();

        $resultat = $this->service->traiter($this->message($plateau, 15000, 1, [
            'ts' => CarbonImmutable::parse('2000-01-01', 'UTC')->timestamp,
        ]));

        $this->assertSame(StatutIngestionMesure::Rejetee, $resultat->statut);
        $this->assertSame(0, Mesure::count());
    }

    public function test_un_ts_de_l_an_3000_est_rejete(): void
    {
        $plateau = $this->plateauActif();

        $resultat = $this->service->traiter($this->message($plateau, 15000, 1, [
            'ts' => CarbonImmutable::parse('3000-01-01', 'UTC')->timestamp,
        ]));

        $this->assertSame(StatutIngestionMesure::Rejetee, $resultat->statut);
        $this->assertSame(0, Mesure::count());
    }

    /**
     * Invariant de sécurité (audit, test obligatoire #Q1) : l'uid réel est
     * celui du topic MQTT, résolu par `IngestionMesures` avant d'appeler ce
     * service — qui écrase tout `uid` présent dans le payload JSON lui-même
     * (ADR 0003). Au niveau du service, seul le champ `uid` du message fait
     * foi pour résoudre le plateau ; un identifiant usurpé ailleurs dans le
     * payload est ignoré.
     */
    public function test_le_champ_uid_du_message_fait_foi_pour_resoudre_le_plateau(): void
    {
        $plateauReel = $this->plateauActif();
        $plateauUsurpe = $this->plateauActif();

        $message = $this->message($plateauReel, 15000, 1, [
            'uid_dans_le_payload' => $plateauUsurpe->uid,
        ]);

        $resultat = $this->service->traiter($message);

        $this->assertSame(StatutIngestionMesure::Rangee, $resultat->statut);
        $this->assertSame($plateauReel->id, $resultat->mesure->plateau_id);
    }
}

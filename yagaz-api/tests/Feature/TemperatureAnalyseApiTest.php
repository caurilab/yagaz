<?php

namespace Tests\Feature;

use App\Enums\NiveauAcces;
use App\Models\Plateau;
use App\Models\SessionCuisson;
use App\Models\Site;
use App\Models\SiteAcces;
use App\Models\Temperature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * `GET /api/sites/{uuid}/temperature/analyse` (ADR 0011, extension) : courbe
 * horaire de température, pic, histogramme des cuissons par heure, heure de
 * pointe/période dominante, fréquence de cuisson et série journalière —
 * cloisonné au périmètre foyer (`site_acces`).
 */
class TemperatureAnalyseApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Site, 2: Plateau}
     */
    private function foyerAvecPlateauActif(): array
    {
        $user = User::factory()->create();
        $site = Site::factory()->create(['cree_par' => $user->id]);

        SiteAcces::forceCreate([
            'site_id' => $site->id,
            'user_id' => $user->id,
            'niveau' => NiveauAcces::Proprietaire->value,
        ]);

        $plateau = Plateau::factory()->actif()->create(['site_id' => $site->id]);

        return [$user, $site, $plateau];
    }

    public function test_renvoie_les_agregats_de_temperature_et_de_cuisson(): void
    {
        [$user, $site, $plateau] = $this->foyerAvecPlateauActif();

        $debutMois = now()->startOfMonth();
        $jour1 = $debutMois->copy()->addDay();
        $jour2 = $debutMois->copy()->addDays(2);
        $jour3 = $debutMois->copy()->addDays(3);

        // Températures : hausse à 7h (jour1), pic à 12h (jour2), 19h (jour3).
        Temperature::create([
            'plateau_id' => $plateau->id, 'site_id' => $site->id,
            'mesure_at' => $jour1->copy()->setTime(7, 0), 'recu_at' => now(), 'temp_c' => 45.0, 'seq' => 1,
        ]);
        Temperature::create([
            'plateau_id' => $plateau->id, 'site_id' => $site->id,
            'mesure_at' => $jour1->copy()->setTime(7, 30), 'recu_at' => now(), 'temp_c' => 42.0, 'seq' => 2,
        ]);
        Temperature::create([
            'plateau_id' => $plateau->id, 'site_id' => $site->id,
            'mesure_at' => $jour2->copy()->setTime(12, 0), 'recu_at' => now(), 'temp_c' => 50.0, 'seq' => 3,
        ]);
        Temperature::create([
            'plateau_id' => $plateau->id, 'site_id' => $site->id,
            'mesure_at' => $jour3->copy()->setTime(19, 0), 'recu_at' => now(), 'temp_c' => 30.0, 'seq' => 4,
        ]);

        // Cuissons : 2 sessions à 7h (jour1, l'heure la plus fréquente), 1 à
        // 12h (jour2), 1 à 19h (jour3).
        SessionCuisson::create([
            'site_id' => $site->id,
            'debut_at' => $jour1->copy()->setTime(7, 0), 'fin_at' => $jour1->copy()->setTime(7, 30),
            'temp_max_c' => 45.0,
        ]);
        SessionCuisson::create([
            'site_id' => $site->id,
            'debut_at' => $jour1->copy()->setTime(7, 15), 'fin_at' => $jour1->copy()->setTime(7, 45),
            'temp_max_c' => 44.0,
        ]);
        SessionCuisson::create([
            'site_id' => $site->id,
            'debut_at' => $jour2->copy()->setTime(12, 0), 'fin_at' => $jour2->copy()->setTime(12, 20),
            'temp_max_c' => 50.0,
        ]);
        SessionCuisson::create([
            'site_id' => $site->id,
            'debut_at' => $jour3->copy()->setTime(19, 0), 'fin_at' => $jour3->copy()->setTime(19, 10),
            'temp_max_c' => 30.0,
        ]);

        Sanctum::actingAs($user);
        $reponse = $this->getJson("/api/sites/{$site->uuid}/temperature/analyse?periode=mois");

        $reponse->assertOk();
        $reponse->assertJsonStructure([
            'data' => [
                'periode', 'debut', 'fin',
                'courbe_horaire',
                'pic_temperature' => ['heure', 'temp_c'],
                'cuisson_par_heure',
                'heure_pointe_cuisson',
                'periode_dominante',
                'frequence' => ['sessions_par_jour', 'jours_cuisine', 'duree_moyenne_min'],
                'serie_journaliere',
                'nb_mesures',
            ],
        ]);

        $donnees = $reponse->json('data');

        // courbe_horaire : 24 points, hour7/12/19 renseignés, le reste null.
        $this->assertCount(24, $donnees['courbe_horaire']);
        $courbeParHeure = collect($donnees['courbe_horaire'])->keyBy('heure');
        $this->assertEqualsWithDelta(43.5, $courbeParHeure[7]['temp_moyenne_c'], 0.01);
        $this->assertEqualsWithDelta(50.0, $courbeParHeure[12]['temp_moyenne_c'], 0.01);
        $this->assertEqualsWithDelta(30.0, $courbeParHeure[19]['temp_moyenne_c'], 0.01);
        $this->assertNull($courbeParHeure[3]['temp_moyenne_c']);

        // pic_temperature : 50°C à 12h.
        $this->assertSame(12, $donnees['pic_temperature']['heure']);
        $this->assertEqualsWithDelta(50.0, $donnees['pic_temperature']['temp_c'], 0.01);

        // cuisson_par_heure : 24 points, 7h a le plus de sessions (2).
        $this->assertCount(24, $donnees['cuisson_par_heure']);
        $histogrammeParHeure = collect($donnees['cuisson_par_heure'])->keyBy('heure');
        $this->assertSame(2, $histogrammeParHeure[7]['sessions']);
        $this->assertSame(60, $histogrammeParHeure[7]['duree_min']);
        $this->assertSame(1, $histogrammeParHeure[12]['sessions']);
        $this->assertSame(20, $histogrammeParHeure[12]['duree_min']);
        $this->assertSame(1, $histogrammeParHeure[19]['sessions']);
        $this->assertSame(0, $histogrammeParHeure[3]['sessions']);

        // heure de pointe et période dominante cohérentes (7h -> matin).
        $this->assertSame(7, $donnees['heure_pointe_cuisson']);
        $this->assertSame('matin', $donnees['periode_dominante']);

        // fréquence : 3 jours distincts avec cuisson, durée moyenne = (30+30+20+10)/4 = 22.5.
        $this->assertSame(3, $donnees['frequence']['jours_cuisine']);
        $this->assertEqualsWithDelta(22.5, $donnees['frequence']['duree_moyenne_min'], 0.01);
        $this->assertGreaterThan(0, $donnees['frequence']['sessions_par_jour']);

        // serie_journaliere : 3 jours (un par jour avec données).
        $this->assertCount(3, $donnees['serie_journaliere']);

        // nb_mesures : 4 relevés sur la période.
        $this->assertSame(4, $donnees['nb_mesures']);
    }

    public function test_un_site_sans_donnee_recoit_une_structure_vide(): void
    {
        [$user, $site] = $this->foyerAvecPlateauActif();

        Sanctum::actingAs($user);
        $reponse = $this->getJson("/api/sites/{$site->uuid}/temperature/analyse");

        $reponse->assertOk();
        $donnees = $reponse->json('data');

        $this->assertCount(24, $donnees['courbe_horaire']);
        $this->assertNull($donnees['courbe_horaire'][0]['temp_moyenne_c']);
        $this->assertNull($donnees['pic_temperature']['heure']);
        $this->assertNull($donnees['pic_temperature']['temp_c']);
        $this->assertCount(24, $donnees['cuisson_par_heure']);
        $this->assertSame(0, $donnees['cuisson_par_heure'][0]['sessions']);
        $this->assertNull($donnees['heure_pointe_cuisson']);
        $this->assertNull($donnees['periode_dominante']);
        $this->assertEquals(0, $donnees['frequence']['sessions_par_jour']);
        $this->assertSame(0, $donnees['frequence']['jours_cuisine']);
        $this->assertEquals(0, $donnees['frequence']['duree_moyenne_min']);
        $this->assertSame([], $donnees['serie_journaliere']);
        $this->assertSame(0, $donnees['nb_mesures']);
    }

    public function test_un_autre_foyer_ne_peut_pas_voir_l_analyse_du_site(): void
    {
        [, $site] = $this->foyerAvecPlateauActif();

        $autreUser = User::factory()->create();
        Sanctum::actingAs($autreUser);

        $this->getJson("/api/sites/{$site->uuid}/temperature/analyse")->assertNotFound();
    }

    public function test_rejette_une_periode_invalide(): void
    {
        [$user, $site] = $this->foyerAvecPlateauActif();
        Sanctum::actingAs($user);

        $this->getJson("/api/sites/{$site->uuid}/temperature/analyse?periode=annee")
            ->assertStatus(422);
    }
}

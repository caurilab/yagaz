<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Throttle de l'authentification (audit sécurité, [ÉLEVÉ] rate-limiting) :
 * `POST /api/auth/login` et `POST /api/auth/register` sont limités par le
 * limiteur nommé `auth` (IP + `telephone`, `AppServiceProvider::boot()`).
 */
class RateLimitingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_login_renvoie_429_apres_trop_de_tentatives(): void
    {
        User::factory()->create([
            'telephone' => '+221770000100',
            'password' => 'mot-de-passe-correct',
        ]);

        $payload = [
            'telephone' => '+221770000100',
            'mot_de_passe' => 'mauvais-mot-de-passe',
        ];

        // Les 6 premières tentatives échouent normalement (mauvais mot de
        // passe) : le throttle ne les bloque pas encore.
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/auth/login', $payload)->assertUnprocessable();
        }

        // La 7e tentative (même IP + même téléphone) est bloquée par le throttle.
        $this->postJson('/api/auth/login', $payload)->assertStatus(429);
    }

    public function test_le_register_renvoie_429_apres_trop_de_tentatives(): void
    {
        $payload = [
            'nom' => 'Foyer Bruteforce',
            'telephone' => '+221770000101',
            'mot_de_passe' => 'un-mot-de-passe',
        ];

        // La 1re tentative crée le compte ; les suivantes échouent (téléphone
        // déjà pris) mais comptent quand même dans le throttle, qui agit
        // avant la validation métier.
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/auth/register', $payload);
        }

        $this->postJson('/api/auth/register', $payload)->assertStatus(429);
    }

    public function test_le_throttle_auth_ne_bloque_pas_un_telephone_different_depuis_la_meme_ip(): void
    {
        User::factory()->create([
            'telephone' => '+221770000102',
            'password' => 'mot-de-passe-correct',
        ]);
        User::factory()->create([
            'telephone' => '+221770000103',
            'password' => 'mot-de-passe-correct',
        ]);

        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/auth/login', [
                'telephone' => '+221770000102',
                'mot_de_passe' => 'mauvais-mot-de-passe',
            ])->assertUnprocessable();
        }

        // Clé de throttle différente (autre téléphone) : pas encore bloqué.
        $this->postJson('/api/auth/login', [
            'telephone' => '+221770000103',
            'mot_de_passe' => 'mot-de-passe-correct',
        ])->assertOk();
    }
}

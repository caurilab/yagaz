<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Authentification par jeton Sanctum (contrat API, §« Authentification »).
 */
class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_cree_un_foyer_et_renvoie_un_token(): void
    {
        $reponse = $this->postJson('/api/auth/register', [
            'nom' => 'Foyer Test',
            'telephone' => '+221770000001',
            'mot_de_passe' => 'un-mot-de-passe',
        ]);

        $reponse->assertCreated();
        $reponse->assertJsonStructure(['token', 'user' => ['uuid', 'nom', 'telephone', 'langue']]);

        $this->assertDatabaseHas('users', ['telephone' => '+221770000001', 'name' => 'Foyer Test']);

        $user = User::where('telephone', '+221770000001')->firstOrFail();
        $this->assertNotSame('un-mot-de-passe', $user->password);
        $this->assertTrue(Hash::check('un-mot-de-passe', $user->password));

        // Un foyer n'a aucun membership (pas d'organisation) — doc 07, §2.
        $this->assertSame(0, $user->memberships()->count());
    }

    public function test_register_refuse_un_telephone_deja_pris(): void
    {
        User::factory()->create(['telephone' => '+221770000002']);

        $reponse = $this->postJson('/api/auth/register', [
            'nom' => 'Doublon',
            'telephone' => '+221770000002',
            'mot_de_passe' => 'un-mot-de-passe',
        ]);

        $reponse->assertUnprocessable();
        $reponse->assertJsonValidationErrors(['telephone']);
    }

    public function test_login_reussit_avec_les_bons_identifiants(): void
    {
        User::factory()->create([
            'telephone' => '+221770000003',
            'password' => 'mot-de-passe-correct',
        ]);

        $reponse = $this->postJson('/api/auth/login', [
            'telephone' => '+221770000003',
            'mot_de_passe' => 'mot-de-passe-correct',
        ]);

        $reponse->assertOk();
        $reponse->assertJsonStructure(['token', 'user' => ['uuid']]);
    }

    public function test_login_echoue_avec_un_mauvais_mot_de_passe(): void
    {
        User::factory()->create([
            'telephone' => '+221770000004',
            'password' => 'mot-de-passe-correct',
        ]);

        $reponse = $this->postJson('/api/auth/login', [
            'telephone' => '+221770000004',
            'mot_de_passe' => 'mauvais-mot-de-passe',
        ]);

        $reponse->assertUnprocessable();
    }

    public function test_me_exige_un_token(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_me_renvoie_l_utilisateur_authentifie(): void
    {
        $user = User::factory()->create(['telephone' => '+221770000005']);
        $token = $user->createToken('api')->plainTextToken;

        $reponse = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/me');

        $reponse->assertOk();
        $reponse->assertJsonPath('user.telephone', '+221770000005');
    }

    public function test_logout_revoque_le_jeton_courant(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->assertSame(1, PersonalAccessToken::count());

        $reponse = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/auth/logout');
        $reponse->assertNoContent();

        $this->assertSame(0, PersonalAccessToken::count());

        // Le guard `sanctum` mémorise l'utilisateur résolu pour la durée
        // d'une requête HTTP réelle (un seul processus) ; en test, plusieurs
        // appels HTTP simulés partagent la même application et donc le même
        // guard déjà résolu. On le force à se ré-résoudre pour vérifier que
        // le jeton révoqué n'authentifie plus une requête suivante.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/me')->assertUnauthorized();
    }
}

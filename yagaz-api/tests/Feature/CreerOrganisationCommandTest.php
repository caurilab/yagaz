<?php

namespace Tests\Feature;

use App\Enums\RoleMembership;
use App\Enums\TypeOrganisation;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Commande d'amorçage `yagaz:creer-organisation` : crée une organisation du
 * haut de la chaîne et, en option, son responsable (compte + membership).
 */
class CreerOrganisationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_cree_un_mandataire_avec_son_responsable(): void
    {
        $this->artisan('yagaz:creer-organisation', [
            'type' => 'mandataire',
            'nom' => 'Mandataire Test',
            '--zone' => 'Abidjan',
            '--responsable-nom' => 'Ama Responsable',
            '--responsable-tel' => '+2250700000070',
            '--mot-de-passe' => 'motdepasse123',
        ])->assertSuccessful();

        $org = Organisation::where('nom', 'Mandataire Test')->firstOrFail();
        $this->assertSame(TypeOrganisation::Mandataire, $org->type);

        $user = User::where('telephone', '+2250700000070')->firstOrFail();
        $this->assertDatabaseHas('memberships', [
            'user_id' => $user->id,
            'organisation_id' => $org->id,
            'role' => RoleMembership::Mandataire->value,
        ]);

        // Le mot de passe imposé permet la connexion.
        $this->postJson('/api/auth/login', [
            'telephone' => '+2250700000070',
            'mot_de_passe' => 'motdepasse123',
        ])->assertOk();
    }

    public function test_rattache_un_depot_a_son_mandataire_parent(): void
    {
        $mandataire = Organisation::factory()->mandataire()->create();

        $this->artisan('yagaz:creer-organisation', [
            'type' => 'depot',
            'nom' => 'Dépôt amorcé',
            '--parent' => $mandataire->uuid,
        ])->assertSuccessful();

        $this->assertDatabaseHas('organisations', [
            'nom' => 'Dépôt amorcé',
            'type' => TypeOrganisation::Depot->value,
            'parent_id' => $mandataire->id,
        ]);
    }

    public function test_refuse_un_type_invalide(): void
    {
        $this->artisan('yagaz:creer-organisation', [
            'type' => 'foyer',
            'nom' => 'Peu importe',
        ])->assertFailed();

        $this->assertDatabaseMissing('organisations', ['nom' => 'Peu importe']);
    }
}

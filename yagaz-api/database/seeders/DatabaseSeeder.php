<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // Note : le trait `WithoutModelEvents` de Laravel est volontairement omis.
    // Il désactive tous les évènements Eloquent pendant tout le seed (y
    // compris les seeders imbriqués), ce qui empêcherait le trait `HasUuid`
    // de générer les UUID des entités (organisations, sites, bouteilles,
    // commandes…) créées ici.

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call([
            FormatsBouteilleSeeder::class,
            DemoSeeder::class,
        ]);
    }
}

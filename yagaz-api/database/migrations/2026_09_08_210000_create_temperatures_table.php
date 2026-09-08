<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chaque température de cuisine reçue d'un plateau (ADR 0011) — série
 * temporelle jumelle de `mesures` (doc 08 §5), mêmes contraintes : hypertable
 * TimescaleDB en production (PostgreSQL) si l'extension est installée, table
 * classique sur SQLite (tests), comportement métier identique.
 *
 * Clé primaire composite (plateau_id, mesure_at, seq), pour les mêmes
 * raisons que `mesures` (voir sa migration) : compatibilité hypertable
 * (inclut la colonne de partitionnement `mesure_at`) et idempotence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temperatures', function (Blueprint $table) {
            $table->foreignId('plateau_id')->constrained('plateaux')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->timestampTz('mesure_at'); // horodatage plateau (ADR 0011, `ts`)
            $table->timestampTz('recu_at'); // horodatage serveur
            $table->decimal('temp_c', 5, 2); // température de la cuisine, °C
            $table->bigInteger('seq'); // anti-doublon/anti-trou (ADR 0011, comme les mesures)

            // PK composite incluant la colonne de partition (obligatoire Timescale).
            $table->primary(['plateau_id', 'mesure_at', 'seq']);
        });

        // Transformation en hypertable TimescaleDB, uniquement si l'extension
        // est présente (voir migration `mesures` pour le détail du raisonnement).
        if (DB::getDriverName() === 'pgsql') {
            $timescaleInstalle = DB::selectOne(
                "SELECT 1 AS ok FROM pg_extension WHERE extname = 'timescaledb'"
            );

            if ($timescaleInstalle !== null) {
                DB::statement("SELECT create_hypertable('temperatures', 'mesure_at', if_not_exists => TRUE, migrate_data => TRUE)");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('temperatures');
    }
};

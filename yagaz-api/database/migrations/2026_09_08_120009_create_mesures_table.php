<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chaque pesée reçue d'un plateau (ADR 0003) — doc 07, §5 `mesures`.
 * Hypertable TimescaleDB en production (PostgreSQL) ; table classique sur
 * SQLite (tests), le comportement métier restant identique.
 *
 * Écart au doc 07 : la clé primaire documentée est composite
 * (plateau_id, mesure_at), mal supportée par Eloquent. On garde un `id`
 * bigint auto-incrémenté comme clé primaire technique, tout en conservant :
 * - l'index unique (plateau_id, seq) qui porte l'idempotence réelle ;
 * - l'index (plateau_id, mesure_at) pour les requêtes de série temporelle,
 *   qui joue le rôle de la PK documentée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mesures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plateau_id')->constrained('plateaux')->cascadeOnDelete();
            $table->foreignId('bouteille_id')->nullable()->constrained('bouteilles')->nullOnDelete();
            $table->timestampTz('mesure_at'); // horodatage plateau (ADR 0003, `ts`)
            $table->timestampTz('recu_at'); // horodatage serveur
            $table->integer('poids_g'); // poids brut
            $table->integer('gaz_g')->nullable(); // poids_g − tare, calculé
            $table->bigInteger('seq'); // anti-doublon/anti-trou (ADR 0003)
            $table->integer('batt_mv')->nullable();
            $table->integer('rssi')->nullable();
            $table->decimal('temp_c', 5, 2)->nullable();

            $table->unique(['plateau_id', 'seq']);
            $table->index(['plateau_id', 'mesure_at']);
        });

        // Transformation en hypertable TimescaleDB : uniquement sur PostgreSQL.
        // Les agrégats continus (Phase 2) ne sont volontairement pas créés ici.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT create_hypertable('mesures', 'mesure_at', if_not_exists => TRUE, migrate_data => TRUE)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mesures');
    }
};

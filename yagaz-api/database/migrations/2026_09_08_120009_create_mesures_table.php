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
 * Clé primaire : TimescaleDB impose que tout index unique d'une hypertable
 * inclue la colonne de partitionnement (`mesure_at`). On adopte donc une clé
 * primaire composite (plateau_id, mesure_at, seq) qui :
 * - est valide pour l'hypertable (elle contient `mesure_at`) ;
 * - porte l'idempotence : un message retransmis (mêmes plateau, ts et seq) est
 *   rejeté comme doublon ;
 * - sert d'index de série temporelle (préfixe plateau_id, mesure_at).
 * (Écart assumé au doc 07 §5, qui prévoyait un `id` technique.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mesures', function (Blueprint $table) {
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

            // PK composite incluant la colonne de partition (obligatoire Timescale).
            $table->primary(['plateau_id', 'mesure_at', 'seq']);
        });

        // Transformation en hypertable TimescaleDB, uniquement si l'extension
        // est présente (prod/CI via l'image timescale, ou dev Docker). Sur un
        // PostgreSQL local sans TimescaleDB, `mesures` reste une table classique :
        // les données sont identiques, seules les optimisations séries temporelles
        // (chunks, agrégats continus) sont absentes. Les agrégats continus
        // arriveront en Phase 2. SQLite : table classique également.
        if (DB::getDriverName() === 'pgsql') {
            $timescaleInstalle = DB::selectOne(
                "SELECT 1 AS ok FROM pg_extension WHERE extname = 'timescaledb'"
            );

            if ($timescaleInstalle !== null) {
                DB::statement("SELECT create_hypertable('mesures', 'mesure_at', if_not_exists => TRUE, migrate_data => TRUE)");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mesures');
    }
};

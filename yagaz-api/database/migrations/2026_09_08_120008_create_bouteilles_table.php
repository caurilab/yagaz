<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Une bouteille suivie par un foyer, posée (ou non) sur un plateau — doc 07,
 * §4 `bouteilles`.
 *
 * Contrainte « au plus une bouteille active par site » : index unique
 * partiel sur (site_id) où role_bouteille = 'active'. Supporté nativement
 * par PostgreSQL et SQLite (syntaxe identique), donc mutualisé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bouteilles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('format_id')->constrained('formats_bouteille')->restrictOnDelete();
            $table->foreignId('plateau_id')->nullable()->constrained('plateaux')->nullOnDelete();
            $table->integer('tare_g')->nullable();
            $table->string('tare_source')->default('nominale');
            $table->boolean('tare_fiable')->default(false);
            $table->string('role_bouteille')->default('active');
            $table->integer('seuil_bas_pct')->default(15);
            $table->timestampsTz();
        });

        DB::statement(
            "CREATE UNIQUE INDEX bouteille_active_unique ON bouteilles (site_id) WHERE role_bouteille = 'active'"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS bouteille_active_unique');

        Schema::dropIfExists('bouteilles');
    }
};

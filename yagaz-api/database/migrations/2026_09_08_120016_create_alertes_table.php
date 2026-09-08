<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trace des franchissements de seuil et propositions, pour éviter le
 * re-spam — doc 07, §8 `alertes`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bouteille_id')->nullable()->constrained('bouteilles')->nullOnDelete();
            $table->foreignId('organisation_id')->nullable()->constrained('organisations')->nullOnDelete();
            $table->string('type');
            $table->string('statut')->default('emise');
            $table->string('canal');
            $table->timestampTz('created_at')->useCurrent();
        });

        // Une alerte doit toujours cibler quelque chose (bouteille ou
        // organisation), jamais orpheline. SQLite ne supporte pas ADD
        // CONSTRAINT CHECK en ALTER, donc limité à pgsql (prod).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE alertes ADD CONSTRAINT alertes_cible_presente CHECK (bouteille_id IS NOT NULL OR organisation_id IS NOT NULL)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('alertes');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sessions de cuisson détectées à partir de la température de cuisine (ADR
 * 0011) : une session est ouverte quand la température dépasse
 * `seuil_cuisson_c` et fermée quand elle redescend, pour la traçabilité
 * (« cuisson en cours », historique, durée) et les analyses futures.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions_cuisson', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->timestampTz('debut_at');
            $table->timestampTz('fin_at')->nullable(); // null tant que la session est ouverte
            $table->decimal('temp_max_c', 5, 2);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions_cuisson');
    }
};

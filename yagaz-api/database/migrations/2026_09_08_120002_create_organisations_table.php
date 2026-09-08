<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un acteur professionnel : dépôt, mandataire ou distributeur. Frontière de
 * cloisonnement (tenant) — doc 07, §2 `organisations`.
 *
 * Écart au doc 07 : la colonne `geo` (type `point`) est remplacée par deux
 * colonnes `lat`/`lng` (decimal 10,7 nullable) pour rester portable entre
 * SQLite (tests) et PostgreSQL (prod), qui n'a pas d'équivalent natif SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type');
            $table->string('nom');
            $table->foreignId('parent_id')->nullable()->constrained('organisations')->nullOnDelete();
            $table->string('zone')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->boolean('abonnement_actif')->default(false);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisations');
    }
};

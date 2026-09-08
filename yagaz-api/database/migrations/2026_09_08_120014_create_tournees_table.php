<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regroupe des livraisons d'un mandataire (ou dépôt) pour une journée —
 * doc 07, §6 `tournees`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignId('livreur_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date');
            $table->string('statut')->default('proposee');
            // Non listés au doc 07 mais ajoutés par cohérence avec le reste du schéma.
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournees');
    }
};

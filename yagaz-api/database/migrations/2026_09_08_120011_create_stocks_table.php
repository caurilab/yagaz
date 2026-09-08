<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * État du stock d'une organisation (dépôt surtout), par format, plein et
 * vide — doc 07, §7 `stocks`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignId('format_id')->constrained('formats_bouteille')->restrictOnDelete();
            $table->integer('pleines')->default(0);
            $table->integer('vides')->default(0);
            $table->integer('seuil_plein_bas')->default(0);
            $table->timestampsTz();

            $table->unique(['organisation_id', 'format_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};

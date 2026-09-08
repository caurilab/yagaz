<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiel des marques de gaz (Oryx, Total, Petro Ivoire…), avec un code
 * couleur hex pour le sélecteur à l'enregistrement d'une bouteille.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marques', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->unique();
            $table->string('couleur');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marques');
    }
};

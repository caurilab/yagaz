<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiel des formats/marques de bouteille (B6, B12, B24…), avec tare
 * nominale — doc 07, §4 `formats_bouteille`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formats_bouteille', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('marque');
            $table->integer('tare_nominale_g');
            $table->integer('contenance_gaz_g');
            $table->timestampsTz();

            $table->unique(['code', 'marque']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formats_bouteille');
    }
};

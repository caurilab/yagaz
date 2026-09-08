<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le capteur physique (plateau de pesée) — doc 07, §4 `plateaux`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plateaux', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique(); // identifiant gravé (PLT-XXXXXX), = login MQTT
            $table->string('secret_hash'); // mot de passe MQTT haché
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->string('statut')->default('provisionne');
            $table->timestampTz('dernier_vu_at')->nullable();
            $table->string('firmware_version')->nullable();
            $table->string('alim')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plateaux');
    }
};

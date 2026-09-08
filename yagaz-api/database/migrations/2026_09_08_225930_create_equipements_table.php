<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registre unifié `equipements` (ADR 0012) : balance, capteur de température
 * ou écran de cuisine. Couche gestion/gating côté utilisateur — l'ingestion
 * du poids reste portée par `plateaux`, celle de la température par le
 * topic MQTT dédié (ADR 0003/0011) ; le lien se fait par `reference` ↔
 * `plateaux.uid` (réconciliation fine des deux tables : chantier ultérieur).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type');
            $table->string('reference')->unique(); // code/lien d'acquisition gravé
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->string('statut')->default('a_connecter');
            $table->foreignId('cree_par')->constrained('users')->restrictOnDelete();
            $table->timestampTz('dernier_vu_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipements');
    }
};

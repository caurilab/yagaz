<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une tournée gagne un `uuid` (Phase 5, contrat API doc 11, §1 :
 * `PATCH /api/tournees/{uuid}`) : comme toute entité exposée au client, jamais
 * l'`id` interne (doc 09, §« Conventions générales »). Colonne posée en
 * complément de la migration `create_tournees_table` (déjà exécutée en
 * Phase 4) plutôt que de la modifier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournees', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('tournees', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};

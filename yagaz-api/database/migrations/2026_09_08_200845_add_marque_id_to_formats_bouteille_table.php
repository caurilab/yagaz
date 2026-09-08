<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lie chaque format de bouteille à sa marque (référentiel `marques`), pour
 * exposer le code couleur (contrat API). La colonne `marque` (string) est
 * conservée pour compatibilité — `marque_id` est nullable, backfillé par
 * `FormatsBouteilleSeeder` (matching sur le nom de marque).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formats_bouteille', function (Blueprint $table) {
            $table->foreignId('marque_id')->nullable()->after('marque')->constrained('marques')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('formats_bouteille', function (Blueprint $table) {
            $table->dropConstrainedForeignId('marque_id');
        });
    }
};

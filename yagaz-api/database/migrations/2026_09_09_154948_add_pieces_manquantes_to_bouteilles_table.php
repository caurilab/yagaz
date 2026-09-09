<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tare ajustable (pièces manquantes) : liste des clés de pièces amovibles
 * (`config('bouteille.pieces_amovibles')`) cochées comme manquantes par le
 * foyer à l'enregistrement de la bouteille. Nullable - comportement inchangé
 * quand absent. `json()` est supporté nativement par PostgreSQL et SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bouteilles', function (Blueprint $table) {
            $table->json('pieces_manquantes')->nullable()->after('calibrage_observations');
        });
    }

    public function down(): void
    {
        Schema::table('bouteilles', function (Blueprint $table) {
            $table->dropColumn('pieces_manquantes');
        });
    }
};

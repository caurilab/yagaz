<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Numéro de contact du dépôt (appel/WhatsApp) - exposé au foyer sur l'écran
 * de suivi de commande en cas de retard. Nullable : compatible pgsql et
 * sqlite, pas de valeur par défaut imposée aux organisations existantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->string('telephone')->nullable()->after('nom');
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn('telephone');
        });
    }
};

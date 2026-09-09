<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute un nom libre optionnel à l'équipement (ex. « Balance cuisine »),
 * pour compléter le couple `type`/`reference` avec un libellé lisible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipements', function (Blueprint $table) {
            $table->string('nom')->nullable()->after('reference');
        });
    }

    public function down(): void
    {
        Schema::table('equipements', function (Blueprint $table) {
            $table->dropColumn('nom');
        });
    }
};

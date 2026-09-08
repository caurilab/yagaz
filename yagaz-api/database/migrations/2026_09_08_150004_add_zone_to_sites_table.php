<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zone (quartier/commune) du site — ADR 0008 : la notification du livreur
 * habituel expose « le nom d'affichage du site et sa zone (pas l'adresse
 * précise à ce stade) ». Distinct de `adresse` (précis, jamais transmis au
 * livreur avant affectation de la livraison) et de `organisations.zone`
 * (zone du dépôt/mandataire, granularité différente).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('zone')->nullable()->after('adresse');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('zone');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Type de commande foyer (v1 « type simple, prix plus tard ») : `echange`
 * (recharge, reprise de la bouteille vide) ou `achat` (bouteille neuve, sans
 * reprise). Nullable avec défaut `echange` pour rester compatible avec les
 * commandes déjà existantes et les chemins applicatifs qui ne le renseignent
 * pas (`CycleCommande::proposer`, réappros). `$table->string()->default()`
 * reste portable pgsql/sqlite (pas de syntaxe spécifique à un moteur).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            $table->string('type')->nullable()->default('echange')->after('origine');
        });
    }

    public function down(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};

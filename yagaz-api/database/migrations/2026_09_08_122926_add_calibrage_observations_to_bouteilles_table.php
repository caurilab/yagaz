<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compteur d'observations de plancher retenues pour le calibrage automatique
 * de la tare (doc 08 §5, `TraitementMesure`/`TareCalibrage`). Non prévu au
 * doc 07 : ajouté pour permettre à l'algorithme de calibrage de savoir quand
 * marquer `tare_fiable = true` (≥ N_CALIBRAGE observations de plancher
 * confirmées) sans dépendre d'un état en mémoire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bouteilles', function (Blueprint $table) {
            $table->unsignedInteger('calibrage_observations')->default(0)->after('tare_fiable');
        });
    }

    public function down(): void
    {
        Schema::table('bouteilles', function (Blueprint $table) {
            $table->dropColumn('calibrage_observations');
        });
    }
};

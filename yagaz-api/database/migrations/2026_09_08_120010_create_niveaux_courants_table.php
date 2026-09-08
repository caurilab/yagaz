<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table de cache du dernier état par bouteille, pour un affichage instantané
 * et le fonctionnement hors ligne côté app — doc 07, §5 `niveaux_courants`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('niveaux_courants', function (Blueprint $table) {
            $table->foreignId('bouteille_id')->primary()->constrained('bouteilles')->cascadeOnDelete();
            $table->integer('gaz_g');
            $table->integer('niveau_pct');
            $table->integer('autonomie_min')->nullable();
            $table->decimal('debit_g_par_h', 8, 2)->nullable();
            $table->timestampTz('calcule_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('niveaux_courants');
    }
};

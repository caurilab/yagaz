<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le foyer peut désigner un livreur prévenu automatiquement au seuil bas —
 * doc 07, §8 `livreur_habituel`.
 *
 * Écart au doc 07 : ajout d'un `id` bigint auto-incrémenté (non listé au
 * doc, qui ne montre que les 3 colonnes métier) pour rester cohérent avec
 * Eloquent. `site_id` est rendu unique : le libellé « désigner UN livreur »
 * (singulier) implique un livreur habituel par site.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('livreur_habituel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->unique()->constrained('sites')->cascadeOnDelete();
            $table->foreignId('livreur_user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('actif')->default(true);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('livreur_habituel');
    }
};

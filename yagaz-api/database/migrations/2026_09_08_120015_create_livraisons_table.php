<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L'exécution physique d'une commande par un livreur — doc 07, §6 `livraisons`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('livraisons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commande_id')->constrained('commandes')->cascadeOnDelete();
            $table->foreignId('livreur_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tournee_id')->nullable()->constrained('tournees')->nullOnDelete();
            $table->string('statut')->default('affectee');
            $table->integer('pleines_deposees')->default(0);
            $table->integer('vides_recuperes')->default(0);
            // Horodatages par transition de statut (doc 07 : « horodatages timestamptz… »).
            $table->timestampTz('affectee_at')->nullable();
            $table->timestampTz('en_route_at')->nullable();
            $table->timestampTz('livree_at')->nullable();
            $table->timestampTz('vide_recupere_at')->nullable();
            $table->timestampsTz();
        });

        // Ajout différé de la FK depuis `mouvements_stock` (table créée avant
        // `livraisons` dans l'ordre du doc 07, §11 — voir migration
        // `create_mouvements_stock_table`).
        Schema::table('mouvements_stock', function (Blueprint $table) {
            $table->foreign('livraison_id')->references('id')->on('livraisons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mouvements_stock', function (Blueprint $table) {
            $table->dropForeign(['livraison_id']);
        });

        Schema::dropIfExists('livraisons');
    }
};

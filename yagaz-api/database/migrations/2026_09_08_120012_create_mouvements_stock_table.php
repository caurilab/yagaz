<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal des variations de stock (vente, retour vide, réappro, ajustement),
 * pour l'historique et l'audit — doc 07, §7 `mouvements_stock`.
 *
 * Note : `livraison_id` référence la table `livraisons`, créée plus tard
 * dans l'ordre du doc 07 (§11). La colonne est donc ajoutée ici sans
 * contrainte de clé étrangère ; celle-ci est posée dans la migration
 * `create_livraisons_table` une fois la table cible disponible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mouvements_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_id')->constrained('stocks')->cascadeOnDelete();
            $table->string('type');
            $table->integer('delta_pleines')->default(0);
            $table->integer('delta_vides')->default(0);
            $table->unsignedBigInteger('livraison_id')->nullable()->index();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mouvements_stock');
    }
};

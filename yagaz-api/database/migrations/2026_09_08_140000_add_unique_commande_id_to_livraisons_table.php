<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une commande = au plus une livraison (doc 10, §4 et §6 : « affecte
 * (éventuellement) un livreur à une commande préparée »). Index UNIQUE en
 * garde-fou base de données contre la double livraison, en complément de la
 * vérification applicative sous verrou dans `CycleCommande::affecterLivreur`
 * (audit sécurité Phase 4, [MOYEN] — races TOCTOU sur l'affectation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('livraisons', function (Blueprint $table) {
            $table->unique('commande_id');
        });
    }

    public function down(): void
    {
        Schema::table('livraisons', function (Blueprint $table) {
            $table->dropUnique(['commande_id']);
        });
    }
};

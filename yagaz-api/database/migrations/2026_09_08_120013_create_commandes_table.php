<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une demande de recharge, émise par un foyer ou un dépôt vers son
 * mandataire — doc 07, §6 `commandes`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commandes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('origine');
            $table->foreignId('demandeur_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('demandeur_org_id')->nullable()->constrained('organisations')->nullOnDelete();
            $table->foreignId('cible_org_id')->constrained('organisations')->restrictOnDelete();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->foreignId('format_id')->constrained('formats_bouteille')->restrictOnDelete();
            $table->integer('quantite');
            $table->string('statut')->default('proposee');
            $table->string('mode_paiement')->default('a_la_livraison');
            $table->string('statut_paiement')->default('en_attente');
            $table->integer('commission_g')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commandes');
    }
};

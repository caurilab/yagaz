<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ledger des paiements Mobile Money (ADR 0010, v2 brique 1) : une commande
 * peut avoir plusieurs tentatives (`initie` → `regle`/`echoue`/`expire`).
 * L'index unique `(provider, reference)` porte l'idempotence des webhooks —
 * une notification rejouée ne peut pas créer une seconde ligne pour la même
 * référence provider.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('commande_id')->constrained('commandes')->restrictOnDelete();
            $table->string('provider');
            // Référence provider : nullable tant que le provider ne l'a pas
            // encore fournie (échec d'initiation), unique avec `provider` une
            // fois connue (idempotence des webhooks, ADR 0010 §Sécurité).
            $table->string('reference')->nullable();
            $table->integer('montant');
            $table->string('devise')->default('XOF');
            $table->string('statut')->default('initie');
            $table->timestampsTz();

            $table->unique(['provider', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements');
    }
};

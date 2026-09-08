<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rend une alerte adressable (Phase 5, contrat API doc 11, §3) : une
 * notification est une alerte qui porte, en plus de sa cible existante
 * (bouteille ou organisation), une référence optionnelle vers la commande
 * concernée, le site du foyer destinataire, et l'utilisateur à notifier
 * (`GET /api/notifications` filtre sur `destinataire_user_id`). Colonnes
 * toutes nullable : une alerte de tension de stock (Phase 4) reste valide
 * sans elles. Migration séparée de `create_alertes_table` (déjà exécutée),
 * conformément à la consigne de ne pas modifier une migration déjà en place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alertes', function (Blueprint $table) {
            $table->foreignId('commande_id')->nullable()->after('organisation_id')->constrained('commandes')->nullOnDelete();
            $table->foreignId('site_id')->nullable()->after('commande_id')->constrained('sites')->nullOnDelete();
            $table->foreignId('destinataire_user_id')->nullable()->after('site_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('alertes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('destinataire_user_id');
            $table->dropConstrainedForeignId('site_id');
            $table->dropConstrainedForeignId('commande_id');
        });
    }
};

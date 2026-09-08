<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Préférences d'alerte d'un foyer (contrat API §Alertes,
 * `PATCH /api/me/reglages-alertes`) : `canaux_alerte` (sous-ensemble de
 * `App\Enums\CanalAlerte`) et un livreur habituel par défaut.
 *
 * Écart au doc 07 : la table `livreur_habituel` existante est posée par
 * `site_id` (un livreur par site). L'endpoint `/api/me/reglages-alertes` est
 * lui posé au niveau du compte, sans site précisé dans le corps de la
 * requête — on ajoute donc ici une préférence de compte
 * (`livreur_habituel_user_id`), simple valeur par défaut du foyer, distincte
 * de l'affectation par site de la Phase 4/5 (notification effective au
 * seuil bas, dispatch vers un livreur pro).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('canaux_alerte')->nullable()->after('langue');
            $table->foreignId('livreur_habituel_user_id')
                ->nullable()
                ->after('canaux_alerte')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('livreur_habituel_user_id');
            $table->dropColumn('canaux_alerte');
        });
    }
};

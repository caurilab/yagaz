<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Étanchéité du livreur tiers (ADR 0008, maillon A de l'ADR 0009) : une
 * notification adressée à un livreur habituel ne doit JAMAIS porter les
 * références foyer (`bouteille_id`/`site_id`) — le `Notificateur` les
 * retirerait de toute façon (invariant anti-fuite existant), mais on ne
 * s'appuie pas sur cet effet de bord : `Notificateur::notifierLivreurHabituel()`
 * ne les renseigne jamais. L'information minimale (nom d'affichage du site,
 * zone, format) est portée par cette colonne `contexte` dédiée, distincte des
 * références relationnelles.
 *
 * Élargit aussi la contrainte `alertes_cible_presente` (posée par
 * `create_alertes_table`, pgsql seulement) : une alerte adressée
 * nommément (`destinataire_user_id`, Phase 5) est une cible valide même sans
 * `bouteille_id`/`organisation_id` — déjà le cas des alertes de proposition/
 * changement de statut de commande (doc 11 §3), et maintenant aussi de
 * celle-ci.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alertes', function (Blueprint $table) {
            $table->json('contexte')->nullable()->after('destinataire_user_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE alertes DROP CONSTRAINT alertes_cible_presente');
            DB::statement('ALTER TABLE alertes ADD CONSTRAINT alertes_cible_presente CHECK (bouteille_id IS NOT NULL OR organisation_id IS NOT NULL OR destinataire_user_id IS NOT NULL)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE alertes DROP CONSTRAINT alertes_cible_presente');
            DB::statement('ALTER TABLE alertes ADD CONSTRAINT alertes_cible_presente CHECK (bouteille_id IS NOT NULL OR organisation_id IS NOT NULL)');
        }

        Schema::table('alertes', function (Blueprint $table) {
            $table->dropColumn('contexte');
        });
    }
};

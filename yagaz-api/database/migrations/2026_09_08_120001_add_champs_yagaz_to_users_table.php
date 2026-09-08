<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Complète la table `users` par défaut de Laravel avec les champs du doc 07
 * (§2 `users`) : `uuid` (identifiant externe), `telephone` (identifiant
 * principal en Afrique de l'Ouest, souvent sans email), `langue`, et rend
 * `email` optionnel.
 *
 * Écart au doc 07 : les colonnes `name`/`password` de Laravel sont conservées
 * telles quelles (au lieu de `nom`/`mot_de_passe`) — la consigne de la Phase 1
 * ne demande que l'ajout de `uuid`, `telephone`, `langue` et le passage de
 * `email` en nullable, pas un renommage des colonnes natives Laravel/Sanctum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->string('telephone')->nullable()->unique()->after('name');
            $table->string('langue', 5)->default('fr')->after('email');
        });

        // Email nullable (la contrainte unique existante est conservée telle
        // quelle par le schema builder lors du `change()`).
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });

        // Renseigne un UUID pour les éventuelles lignes déjà présentes.
        foreach (DB::table('users')->whereNull('uuid')->get(['id']) as $user) {
            DB::table('users')->where('id', $user->id)->update(['uuid' => (string) Str::uuid()]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
            $table->dropUnique(['telephone']);
            $table->dropColumn('telephone');
            $table->dropColumn('langue');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};

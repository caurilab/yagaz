<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Filet dur d'idempotence pour les réappros (ADR 0009, maillon D ; audit
 * sécurité, [FAIBLE] filet dur d'idempotence réappro), EN COMPLÉMENT du
 * verrou applicatif existant (`CycleCommande::preparer()` verrouille le
 * stock, dans la même transaction que `PreparationReappro::preparer()`, qui
 * relit les réappros non terminés avant d'en créer un nouveau).
 *
 * Index UNIQUE PARTIEL sur `(demandeur_org_id, cible_org_id, format_id)`,
 * filtré aux réappros **non terminés** (`origine = 'depot'` ET `statut IN
 * (proposee, confirmee, preparee, en_livraison)`) : au plus un réappro actif
 * par triplet (dépôt demandeur, mandataire cible, format), quel que soit le
 * chemin applicatif qui tenterait d'en insérer un second (bug, appel direct,
 * job dupliqué…). `livree`/`annulee` sont hors filtre : un nouveau réappro
 * peut légitimement suivre un réappro terminé.
 *
 * **PostgreSQL uniquement** (prod) : SQLite ne supporte pas les index
 * partiels avec la syntaxe utilisée ailleurs dans le projet pour rester
 * portable (cf. `2026_09_08_120002_create_organisations_table.php`), et les
 * tests tournent sur SQLite — le verrou applicatif y reste donc la SEULE
 * garantie testée. Cette contrainte est un filet de secours, pas le
 * mécanisme principal d'idempotence.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX commandes_reappro_non_termine_unique
            ON commandes (demandeur_org_id, cible_org_id, format_id)
            WHERE origine = 'depot' AND statut IN ('proposee', 'confirmee', 'preparee', 'en_livraison')
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS commandes_reappro_non_termine_unique');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lignes d'une tournée de mandataire (Phase 5, contrat API doc 11, §1) : pour
 * chaque dépôt visité et chaque format concerné, les bouteilles pleines à
 * déposer et les vides à récupérer (logistique inversée). Table non prévue
 * au doc 07 (antérieur à cette phase) mais nécessaire pour porter le détail
 * de `POST /api/mandataires/{orgUuid}/tournees` et
 * `PATCH /api/tournees/{uuid}` — une tournée (doc 07 §6) reste l'entité
 * mère, ses lignes en détaillent le contenu par dépôt/format.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournee_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournee_id')->constrained('tournees')->cascadeOnDelete();
            $table->foreignId('depot_organisation_id')->constrained('organisations')->restrictOnDelete();
            $table->foreignId('format_id')->constrained('formats_bouteille')->restrictOnDelete();
            $table->integer('pleines')->default(0);
            $table->integer('vides_a_recuperer')->default(0);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournee_lignes');
    }
};

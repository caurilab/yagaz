<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qui peut voir/gérer quel site, et avec quel niveau — cœur du multi-sites et
 * du cloisonnement entre foyers (doc 07, §3 `site_acces`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_acces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('niveau');
            $table->timestampsTz();

            $table->unique(['site_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_acces');
    }
};

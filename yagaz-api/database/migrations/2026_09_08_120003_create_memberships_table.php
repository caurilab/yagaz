<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rattache un `user` à une `organisation` avec un rôle — doc 07, §2 `memberships`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('role');
            $table->boolean('actif')->default(true);
            $table->timestampsTz();

            $table->unique(['user_id', 'organisation_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};

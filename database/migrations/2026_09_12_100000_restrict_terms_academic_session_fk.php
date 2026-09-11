<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2: prevent cascade-delete of academic terms when a session is deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terms', function (Blueprint $table) {
            $table->dropForeign(['academic_session_id']);
        });

        Schema::table('terms', function (Blueprint $table) {
            $table->foreign('academic_session_id')
                ->references('id')
                ->on('academic_sessions')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('terms', function (Blueprint $table) {
            $table->dropForeign(['academic_session_id']);
        });

        Schema::table('terms', function (Blueprint $table) {
            $table->foreign('academic_session_id')
                ->references('id')
                ->on('academic_sessions')
                ->cascadeOnDelete();
        });
    }
};

<?php
// database/migrations/2025_11_18_100000_add_academic_session_id_to_student_class_section_pivot.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add academic_session_id to student_class_section_pivot table.
 *
 * The create migration already defines academic_session_id on some histories;
 * guard so SQLite Feature tests do not fail with "duplicate column name".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('student_class_section_pivot', 'academic_session_id')) {
            return;
        }

        Schema::table('student_class_section_pivot', function (Blueprint $table) {
            $table->foreignUuid('academic_session_id')
                  ->after('class_section_id')
                  ->constrained('academic_sessions')
                  ->onDelete('cascade');

            $table->unique(
                ['student_id', 'class_section_id', 'academic_session_id'],
                'student_section_per_session_unique'
            );

            $table->index(['academic_session_id', 'student_id'], 'session_student_index');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('student_class_section_pivot', 'academic_session_id')) {
            return;
        }

        Schema::table('student_class_section_pivot', function (Blueprint $table) {
            $table->dropForeign(['academic_session_id']);
            $table->dropColumn('academic_session_id');
            $table->dropUnique('student_section_per_session_unique');
            $table->dropIndex('session_student_index');
        });
    }
};

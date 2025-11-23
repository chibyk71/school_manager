<?php
// database/migrations/2025_11_18_100000_add_academic_session_id_to_student_class_section_pivot.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add academic_session_id to student_class_section_pivot table.
 *
 * This migration makes student class/section assignments session-aware,
 * enabling accurate historical tracking and correct promotion logic.
 *
 * Why this is required:
 * - A student is in JSS1-A in 2025/2026 session
 * - They promote to JSS2-B in 2026/2027 session
 * - Without session context, we cannot know which assignment is "current"
 * - This enables proper promotion execution and reporting
 *
 * @author  Your Name
 * @since   2025-11-18
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('student_class_section_pivot', function (Blueprint $table) {
            // Add the academic session foreign key
            $table->foreignUuid('academic_session_id')
                  ->after('class_section_id')
                  ->constrained('academic_sessions')
                  ->onDelete('cascade');

            // Make the combination unique per session
            // A student can only be in one section per academic session
            $table->unique(
                ['student_id', 'class_section_id', 'academic_session_id'],
                'student_section_per_session_unique'
            );

            // Optional: index for performance on current session queries
            $table->index(['academic_session_id', 'student_id'], 'session_student_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('student_class_section_pivot', function (Blueprint $table) {
            // Drop the foreign key and column
            $table->dropForeign(['academic_session_id']);
            $table->dropColumn('academic_session_id');

            // Drop the unique and index constraints
            $table->dropUnique('student_section_per_session_unique');
            $table->dropIndex('session_student_index');
        });
    }
};

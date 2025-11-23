<?php
// database/migrations/2025_11_19_100400_add_admission_session_id_and_number_to_applications.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_applications', function (Blueprint $table) {
            // Official admission number (e.g. CHR/2025/0847) – generated only on acceptance
            $table->string('admission_number')->nullable()->unique()->after('reference_number');

            // Final assigned class section after acceptance
            $table->foreignUuid('assigned_class_section_id')
                  ->nullable()
                  ->after('student_id')
                  ->constrained('class_sections')
                  ->nullOnDelete();

            // Track which session this student was admitted into
            $table->foreignUuid('final_academic_session_id')
                  ->nullable()
                  ->after('admission_session_id')
                  ->constrained('academic_sessions')
                  ->nullOnDelete();

            // Indexes
            $table->index('admission_number');
            $table->index('status');
            $table->index(['school_id', 'admission_session_id']);
        });
    }

    public function down(): void
    {
        Schema::table('admission_applications', function (Blueprint $table) {
            $table->dropColumn([
                'admission_number',
                'assigned_class_section_id',
                'final_academic_session_id'
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Create Grades Table (2025_01_23_114159_create_grades_table)
 *
 * Creates the grades table used to define grading scales per school and optional school section.
 * Each grade represents a performance band (e.g., A = 80–100, B = 70–79) with a unique code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('school_id')
                ->constrained('schools')
                ->cascadeOnDelete()
                ->comment('Mandatory: owning school');

            $table->uuid('school_section_id')->nullable()
                ->comment('Optional: section-specific grade scale override');

            $table->string('name', 100)
                ->index()
                ->comment('Human-readable name, e.g., "Excellent", "A", "Distinction"');

            $table->string('code', 10)
                ->index()
                ->comment('Short unique code, e.g., "A", "B+", "7" (WAEC style)');

            $table->unsignedInteger('min_score')
                ->comment('Inclusive minimum score for this grade (0–100)');

            $table->unsignedInteger('max_score')
                ->comment('Inclusive maximum score for this grade (0–100)');

            $table->text('remark')
                ->nullable()
                ->comment('Optional description/interpretation, shown on report cards');

            $table->softDeletes();
            $table->timestamps();

            $table->unique(
                ['school_id', 'school_section_id', 'code'],
                'uniq_grades_school_section_code'
            );

            $table->index(['min_score', 'max_score'], 'idx_grades_score_range');

            // Blueprint has no raw() method; SQLite Feature tests fail on $table->raw().
            // Check constraint applied below for mysql/pgsql only.
        });

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'pgsql'], true)) {
            try {
                Schema::getConnection()->statement(
                    'ALTER TABLE grades ADD CONSTRAINT chk_grades_min_max CHECK (min_score <= max_score)'
                );
            } catch (\Throwable $e) {
                // Ignore if constraint already exists or engine rejects it.
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};

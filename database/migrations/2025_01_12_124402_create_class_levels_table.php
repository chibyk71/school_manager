<?php

/**
 * Migration: create_class_levels_table
 *
 * Creates the class_levels table which stores academic year/stage definitions
 * for each school section (e.g. JSS 1, Primary 6, Form 1).
 *
 * Design decisions:
 * - No school_id column: tenant scoping flows through school_section_id → school_sections.school_id
 *   ClassLevel uses BelongsToPrimaryModel trait pointing to SchoolSection, not BelongsToSchool directly.
 * - sequence: drives promotion logic (next level = sequence + 1 within same section)
 * - max_arms: nullable soft cap on number of streams/classrooms under this level
 * - alias: school-customizable short label (e.g. "JS1" instead of "JSS 1")
 * - display_name: formal long name (e.g. "Junior Secondary School One")
 * - description: optional notes about the level
 * - is_active: allows disabling a level without deleting it
 * - Soft deletes: recoverable via trash toggle, blocked if students are assigned
 *
 * Unique constraints:
 * - name must be unique per section (JSS 1 cannot appear twice in same section)
 * - sequence must be unique per section (no two levels at same position)
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_levels', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('school_section_id')
                ->constrained('school_sections')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('display_name')->nullable();
            $table->string('alias')->nullable();
            $table->text('description')->nullable();

            $table->unsignedSmallInteger('sequence')
                ->default(1)
                ->comment('Determines ordering and promotion path within a section');

            $table->unsignedSmallInteger('max_arms')
                ->nullable()
                ->comment('Soft cap on number of streams/classrooms under this level');

            $table->boolean('is_active')->default(true);

            $table->softDeletes();
            $table->timestamps();

            // No two levels in the same section can share a name
            $table->unique(['school_section_id', 'name'], 'class_levels_unique_name');

            // No two levels in the same section can share a sequence position
            $table->unique(['school_section_id', 'sequence'], 'class_levels_unique_sequence');

            // Fast lookups when filtering by section
            $table->index('school_section_id');

            // Fast lookups when ordering levels within a section
            $table->index(['school_section_id', 'sequence'], 'class_levels_section_sequence');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_levels');
    }
};

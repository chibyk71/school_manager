<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: create_school_section_staff_table.php
 * (recommended name: school_section_staff — alphabetical + clear intent)
 *
 * Many-to-many pivot between staff positions and school sections.
 * Allows one staff member to work in multiple sections within a school
 * (vice-principal, music teacher, counselor, support staff, etc.).
 *
 * Features / Problems Solved:
 * - Supports multiple section assignments per staff position
 * - Enables section-specific filtering, reporting, permissions
 * - Timestamps track when assignments were made/changed
 * - Cascade on delete keeps data clean
 * - Unique constraint prevents duplicate assignments
 * - Future-proof: easy to add columns (is_coordinator, priority, role, etc.)
 *
 * Fits into User Management / HRM Module:
 * - Replaces single section_id on staff table
 * - Used in StaffController, section assignment modal
 * - Powers queries like: "all staff teaching in primary section"
 * - Integrates with frontend: multi-select section picker in StaffAssignmentModal.vue
 * - Aligns with real school workflows (cross-section roles are common)
 */

return new class extends Migration {
    public function up(): void
    {
        Schema::create('school_section_staff', function (Blueprint $table) {
            // No auto-increment id needed — composite key is enough
            $table->foreignUuid('school_section_id')
                ->constrained('school_sections')
                ->cascadeOnDelete();

            $table->foreignUuid('staff_id')
                ->constrained('staff')
                ->cascadeOnDelete();

            // Optional useful metadata
            $table->boolean('is_coordinator')->default(false);     // e.g. section head
            $table->integer('priority')->default(99);              // sorting/display order
            $table->string('role')->nullable();                    // e.g. "class teacher", "subject teacher" (dynamic enum?)
            $table->text('notes')->nullable();                     // internal memo

            $table->timestamps();

            // Composite primary key + unique constraint
            $table->primary(['school_section_id', 'staff_id']);
            $table->unique(['staff_id', 'school_section_id'], 'staff_section_unique');

            // Indexes for performance
            $table->index('staff_id');
            $table->index('is_coordinator');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_section_staff');
    }
};

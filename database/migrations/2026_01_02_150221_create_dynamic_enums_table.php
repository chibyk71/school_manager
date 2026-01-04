<?php
/**
 * database/migrations/2026_01_02_000001_create_dynamic_enums_table.php
 *
 * This migration creates the `dynamic_enums` table, which stores customizable "enum-like" option lists
 * for model properties in a multi-tenant environment.
 *
 * Features / Problems Solved:
 * - Provides a dedicated, normalized storage for dynamic options (e.g., gender, title, profile_type)
 *   that were previously mixed in the generic `configs` table.
 * - Supports both system-wide defaults (school_id = null) and school-specific overrides/extensions.
 * - Stores options as JSON for flexibility (array of {value: string, label: string, color?: string}).
 * - Ensures uniqueness: a school cannot define the same name + applies_to twice.
 * - Indexes critical columns for fast lookups in scopes (visibleToSchool, forModel).
 * - Uses UUID primary key and foreign UUID for school_id (consistent with the rest of the app).
 * - Cascade on delete for school_id to keep data clean when a school is removed.
 *
 * Fits into the DynamicEnums Module:
 * - This table is the single source of truth for all dynamic option definitions.
 * - Replaces the subset of rows in the existing `configs` table that were used for enum-style options
 *   (title, gender, profile_type, address type, etc.).
 * - Allows future expansion (e.g., ordering, icons, disabled flags) without schema changes.
 * - Works seamlessly with BelongsToSchool trait, HasTableQuery, Activitylog, and the upcoming
 *   HasDynamicEnum trait.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dynamic_enums', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');                  // machine name, e.g., 'gender', 'title'
            $table->string('label');                 // UI label, e.g., 'Gender'
            $table->string('applies_to');            // Fully qualified model class, e.g., App\Models\Profile
            $table->mediumText('description')->nullable();
            $table->string('color')->nullable();     // Optional Tailwind class for badges/previews
            $table->json('options');                 // [{value: 'male', label: 'Male', color?: 'bg-blue-100'}, ...]
            $table->foreignUuid('school_id')
                  ->nullable()
                  ->constrained('schools')
                  ->cascadeOnDelete();

            $table->timestamps();

            // Critical for data integrity and performance
            $table->unique(['name', 'applies_to', 'school_id']);
            $table->index(['applies_to']);
            $table->index(['school_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dynamic_enums');
    }
};

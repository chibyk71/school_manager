<?php

/**
 * Dynamic Enum Phase 1 — normalized definition schema.
 *
 * Replaces the legacy JSON-options Dynamic Enum design.
 *
 * Identity:
 *   - Default (tenant-wide): school_id IS NULL + key
 *   - School customization:  school_id = S + key
 *
 * Uniqueness is enforced with partial unique indexes so that:
 *   - At most one default definition exists per key
 *   - At most one definition exists per (school_id, key)
 *   - Default and school definitions for the same key may coexist
 *
 * SQLite (test) and PostgreSQL support partial unique indexes.
 * MySQL/MariaDB do not; application-level enforcement is deferred to later phases
 * if/when those engines become primary.
 *
 * Options live in dynamic_enum_options (first-class rows), not JSON.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dynamic_enums', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('school_id')
                ->nullable()
                ->constrained('schools')
                ->cascadeOnDelete();

            // Stable machine-readable identity (e.g. expense.type, profile.gender)
            $table->string('key');

            // Human-facing presentation
            $table->string('label');
            $table->mediumText('description')->nullable();

            $table->timestamps();

            // Non-unique indexes for common lookups
            $table->index('key');
            $table->index('school_id');
        });

        $this->addKeyUniquenessIndexes();
    }

    public function down(): void
    {
        Schema::dropIfExists('dynamic_enums');
    }

    /**
     * Enforce (NULL, key) and (school_id, key) uniqueness where the engine supports it.
     */
    private function addKeyUniquenessIndexes(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            // At most one default definition per key
            DB::statement(
                'CREATE UNIQUE INDEX dynamic_enums_default_key_unique ON dynamic_enums (key) WHERE school_id IS NULL'
            );

            // At most one school-specific definition per (school, key)
            DB::statement(
                'CREATE UNIQUE INDEX dynamic_enums_school_key_unique ON dynamic_enums (school_id, key) WHERE school_id IS NOT NULL'
            );

            return;
        }

        // Fallback for engines without partial unique indexes (e.g. MySQL):
        // composite unique still helps for non-null school_id; NULL duplicates
        // remain an application concern until engine support or a later phase.
        Schema::table('dynamic_enums', function (Blueprint $table) {
            $table->unique(['school_id', 'key'], 'dynamic_enums_school_key_unique');
        });
    }
};

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
 * Uniqueness must hold on every supported engine:
 *   - At most one default definition per key
 *   - At most one definition per (school_id, key)
 *   - Default and school definitions for the same key may coexist
 *
 * Strategy by driver:
 *   - SQLite / PostgreSQL: partial unique indexes (WHERE school_id IS NULL / IS NOT NULL)
 *   - MySQL / MariaDB: STORED generated ownership_scope column + unique (ownership_scope, key)
 *     so that NULL school_id participates in uniqueness (normal UNIQUE allows multiple NULLs)
 *
 * ownership_scope is a database-only uniqueness helper (nil UUID when school_id IS NULL).
 * It is not an application identity field and is not mass-assigned.
 *
 * Options live in dynamic_enum_options (first-class rows), not JSON.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sentinel UUID used only for uniqueness of default (school_id IS NULL) rows.
     * Must never collide with a real schools.id.
     */
    private const DEFAULT_OWNERSHIP_SCOPE = '00000000-0000-0000-0000-000000000000';

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
     * Enforce ownership identity at the database level on every supported engine.
     */
    private function addKeyUniquenessIndexes(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $nil = self::DEFAULT_OWNERSHIP_SCOPE;

        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            // Partial unique indexes: NULL defaults and school rows are separate identity spaces.
            DB::statement(
                'CREATE UNIQUE INDEX dynamic_enums_default_key_unique ON dynamic_enums (key) WHERE school_id IS NULL'
            );
            DB::statement(
                'CREATE UNIQUE INDEX dynamic_enums_school_key_unique ON dynamic_enums (school_id, key) WHERE school_id IS NOT NULL'
            );

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            // MySQL/MariaDB treat NULL as distinct in UNIQUE indexes, so multiple
            // (NULL, same-key) rows would otherwise be allowed. A STORED generated
            // column maps NULL school_id to a fixed sentinel so uniqueness holds.
            DB::statement("
                ALTER TABLE dynamic_enums
                ADD COLUMN ownership_scope CHAR(36)
                    CHARACTER SET ascii COLLATE ascii_bin
                    GENERATED ALWAYS AS (IFNULL(school_id, '{$nil}')) STORED
                    NOT NULL
            ");
            DB::statement(
                'CREATE UNIQUE INDEX dynamic_enums_ownership_key_unique ON dynamic_enums (ownership_scope, `key`)'
            );

            return;
        }

        // Unknown engine: best-effort composite unique (does not fully protect NULL defaults).
        Schema::table('dynamic_enums', function (Blueprint $table) {
            $table->unique(['school_id', 'key'], 'dynamic_enums_school_key_unique');
        });
    }
};

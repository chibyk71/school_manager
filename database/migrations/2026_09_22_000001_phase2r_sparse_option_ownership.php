<?php

/**
 * Phase 2R — sparse school option overlay.
 *
 * Moves ownership from whole school definitions to per-option school_id:
 *   school_id IS NULL  → tenant baseline option
 *   school_id = S      → school sparse overlay / school-only option
 *
 * Uniqueness: one value per (definition, ownership scope).
 * Same value may exist at tenant scope and independently per school.
 *
 * Definitions remain application-owned (one per key; school_id stays null).
 * School-level definition rows are not used for option ownership.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DEFAULT_OWNERSHIP_SCOPE = '00000000-0000-0000-0000-000000000000';

    public function up(): void
    {
        // Drop Phase 1 unique that blocked same value across tenant and school scopes.
        Schema::table('dynamic_enum_options', function (Blueprint $table) {
            $table->dropUnique('dynamic_enum_options_enum_value_unique');
        });

        Schema::table('dynamic_enum_options', function (Blueprint $table) {
            $table->foreignUuid('school_id')
                ->nullable()
                ->after('dynamic_enum_id')
                ->constrained('schools')
                ->cascadeOnDelete();

            $table->index('school_id');
        });

        $this->addOptionOwnershipUniqueness();
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement('DROP INDEX IF EXISTS dynamic_enum_options_default_value_unique');
            DB::statement('DROP INDEX IF EXISTS dynamic_enum_options_school_value_unique');
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            Schema::table('dynamic_enum_options', function (Blueprint $table) {
                $table->dropIndex('dynamic_enum_options_ownership_value_unique');
            });
            if (Schema::hasColumn('dynamic_enum_options', 'ownership_scope')) {
                Schema::table('dynamic_enum_options', function (Blueprint $table) {
                    $table->dropColumn('ownership_scope');
                });
            }
        }

        Schema::table('dynamic_enum_options', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->dropColumn('school_id');
        });

        Schema::table('dynamic_enum_options', function (Blueprint $table) {
            $table->unique(['dynamic_enum_id', 'value'], 'dynamic_enum_options_enum_value_unique');
        });
    }

    private function addOptionOwnershipUniqueness(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $nil = self::DEFAULT_OWNERSHIP_SCOPE;

        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement(
                'CREATE UNIQUE INDEX dynamic_enum_options_default_value_unique
                 ON dynamic_enum_options (dynamic_enum_id, value)
                 WHERE school_id IS NULL'
            );
            DB::statement(
                'CREATE UNIQUE INDEX dynamic_enum_options_school_value_unique
                 ON dynamic_enum_options (dynamic_enum_id, school_id, value)
                 WHERE school_id IS NOT NULL'
            );

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("
                ALTER TABLE dynamic_enum_options
                ADD COLUMN ownership_scope CHAR(36)
                    CHARACTER SET ascii COLLATE ascii_bin
                    GENERATED ALWAYS AS (IFNULL(school_id, '{$nil}')) STORED
                    NOT NULL
            ");
            DB::statement(
                'CREATE UNIQUE INDEX dynamic_enum_options_ownership_value_unique
                 ON dynamic_enum_options (dynamic_enum_id, ownership_scope, value)'
            );

            return;
        }

        // Fallback: composite unique (weaker for NULL school_id on some engines)
        Schema::table('dynamic_enum_options', function (Blueprint $table) {
            $table->unique(
                ['dynamic_enum_id', 'school_id', 'value'],
                'dynamic_enum_options_ownership_value_unique'
            );
        });
    }
};

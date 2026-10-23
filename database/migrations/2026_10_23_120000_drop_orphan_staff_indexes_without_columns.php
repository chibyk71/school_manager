<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repair: staff create migration previously declared indexes on section_id and
 * department_id without ever adding those columns. That left SQLite unable to
 * rebuild unrelated tables (e.g. devices drop column) with:
 *   error in index staff_school_id_section_id_index after drop column:
 *   no such column: section_id
 *
 * Production MySQL/MariaDB typically rejected the bad CREATE INDEX at migrate
 * time, or never had the orphan indexes. This migration is defensive and
 * idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff')) {
            return;
        }

        $this->dropIndexIfExists('staff', 'staff_school_id_section_id_index');
        $this->dropIndexIfExists('staff', 'staff_department_id_index');

        // Ensure school scoping index exists (replacement for the invalid composite).
        if (Schema::hasColumn('staff', 'school_id') && ! $this->indexExists('staff', 'staff_school_id_index')) {
            Schema::table('staff', function (Blueprint $table) {
                $table->index('school_id', 'staff_school_id_index');
            });
        }
    }

    public function down(): void
    {
        // Do not recreate indexes on non-existent columns.
        if (Schema::hasTable('staff') && $this->indexExists('staff', 'staff_school_id_index')) {
            Schema::table('staff', function (Blueprint $table) {
                $table->dropIndex('staff_school_id_index');
            });
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (! $this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($index) {
            $blueprint->dropIndex($index);
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $rows = DB::select("PRAGMA index_list('{$table}')");
            foreach ($rows as $row) {
                $name = is_object($row) ? ($row->name ?? null) : ($row['name'] ?? null);
                if ($name === $index) {
                    return true;
                }
            }

            return false;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $database = Schema::getConnection()->getDatabaseName();
            $rows = DB::select(
                'select 1 from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ? limit 1',
                [$database, $table, $index]
            );

            return $rows !== [];
        }

        if ($driver === 'pgsql') {
            $rows = DB::select(
                'select 1 from pg_indexes where tablename = ? and indexname = ? limit 1',
                [$table, $index]
            );

            return $rows !== [];
        }

        // Unknown driver: attempt drop via try/catch in caller only when we think it exists.
        return false;
    }
};

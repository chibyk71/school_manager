<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repair (must run BEFORE 2026_02_15_111555_remove_ip_from_devices_table).
 *
 * Staff create historically declared indexes on section_id / department_id without
 * those columns. SQLite then fails later when rebuilding devices (drop column ip):
 *   error in index staff_school_id_section_id_index after drop column:
 *   no such column: section_id
 *
 * Fresh installs that use the corrected create_staff_table never create the bad
 * indexes; this migration is then a no-op.
 *
 * Existing SQLite databases that already applied the bad staff create but have
 * not yet successfully applied remove_ip_from_devices can reach a healthy state
 * because this file sorts immediately before that migration.
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

        if (Schema::hasColumn('staff', 'school_id') && ! $this->indexExists('staff', 'staff_school_id_index')) {
            Schema::table('staff', function (Blueprint $table) {
                $table->index('school_id', 'staff_school_id_index');
            });
        }
    }

    public function down(): void
    {
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

        return false;
    }
};

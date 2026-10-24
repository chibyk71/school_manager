<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Second idempotent pass of the orphan staff-index repair.
 *
 * Primary fix for the SQLite devices.ip failure path is
 * 2026_02_15_111554_drop_orphan_staff_indexes_without_columns (runs before
 * remove_ip_from_devices). This later migration remains so installs that
 * already migrated past devices (e.g. MySQL) still drop any residual bad
 * indexes without requiring a rename of historical migration files.
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
        // no-op: do not recreate indexes on non-existent columns
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

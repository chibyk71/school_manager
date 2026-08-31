<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4: enforce one Student capacity record per Profile within a School.
 *
 * Existing non-unique index idx_students_school_profile is replaced by a unique
 * constraint so concurrent finalization cannot create duplicate Student rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop the non-unique composite index if present (name from create_students migration).
        $this->dropIndexIfExists('students', 'idx_students_school_profile');

        Schema::table('students', function (Blueprint $table) {
            $table->unique(['school_id', 'profile_id'], 'uq_students_school_profile');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique('uq_students_school_profile');
            $table->index(['school_id', 'profile_id'], 'idx_students_school_profile');
        });
    }

    protected function dropIndexIfExists(string $table, string $index): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite: attempt drop; ignore if missing.
            try {
                Schema::table($table, function (Blueprint $blueprint) use ($index) {
                    $blueprint->dropIndex($index);
                });
            } catch (\Throwable) {
                // index may not exist
            }

            return;
        }

        $schema = Schema::getConnection()->getDatabaseName();
        $exists = false;

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $exists = DB::table('information_schema.statistics')
                ->where('table_schema', $schema)
                ->where('table_name', $table)
                ->where('index_name', $index)
                ->exists();
        } elseif ($driver === 'pgsql') {
            $exists = DB::table('pg_indexes')
                ->where('tablename', $table)
                ->where('indexname', $index)
                ->exists();
        }

        if ($exists) {
            Schema::table($table, function (Blueprint $blueprint) use ($index) {
                $blueprint->dropIndex($index);
            });
        }
    }
};

<?php

/**
 * Evolve admissions for Student Lifecycle Phase 1.
 *
 * Goals (deterministic across mysql, pgsql, sqlite):
 *   - student_id nullable
 *   - school_section_id nullable
 *   - roll_no nullable
 *   - application_id nullable FK → student_applications
 *   - offer lifecycle timestamp columns + notes
 *
 * Fresh installs already get the Phase 1 shape from create_admissions_table.
 * This migration only mutates legacy schemas that still have NOT NULL columns.
 *
 * Failures are not swallowed: schema introspection decides what to do; unexpected
 * SQL errors propagate.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admissions')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($this->columnIsNotNullable('admissions', 'student_id')
            || $this->columnIsNotNullable('admissions', 'school_section_id')
            || $this->columnIsNotNullable('admissions', 'roll_no')) {
            if ($driver === 'sqlite') {
                $this->rebuildAdmissionsForSqlite();
            } else {
                $this->softenNullabilityForServer($driver);
            }
        }

        Schema::table('admissions', function (Blueprint $table) {
            if (! Schema::hasColumn('admissions', 'application_id')) {
                $table->foreignUuid('application_id')
                    ->nullable()
                    ->after('student_id')
                    ->constrained('student_applications')
                    ->nullOnDelete();
            }

            foreach ([
                'offered_at',
                'acceptance_deadline',
                'accepted_at',
                'declined_at',
                'expired_at',
                'cancelled_at',
            ] as $col) {
                if (! Schema::hasColumn('admissions', $col)) {
                    $table->timestamp($col)->nullable();
                }
            }

            if (! Schema::hasColumn('admissions', 'notes')) {
                $table->text('notes')->nullable();
            }
        });

        if (! $this->indexExists('admissions', 'idx_admissions_application')
            && Schema::hasColumn('admissions', 'application_id')) {
            Schema::table('admissions', function (Blueprint $table) {
                $table->index('application_id', 'idx_admissions_application');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('admissions')) {
            return;
        }

        Schema::table('admissions', function (Blueprint $table) {
            if (Schema::hasColumn('admissions', 'application_id')) {
                $table->dropForeign(['application_id']);
                $table->dropColumn('application_id');
            }

            foreach (['offered_at', 'acceptance_deadline', 'accepted_at', 'declined_at', 'expired_at', 'cancelled_at', 'notes'] as $col) {
                if (Schema::hasColumn('admissions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    private function softenNullabilityForServer(string $driver): void
    {
        $this->dropForeignIfExists('admissions', 'student_id');
        $this->dropForeignIfExists('admissions', 'school_section_id');
        $this->dropUniqueIfExists('admissions', 'admissions_roll_no_unique');

        if ($driver === 'mysql') {
            if ($this->columnIsNotNullable('admissions', 'student_id')) {
                DB::statement('ALTER TABLE admissions MODIFY student_id CHAR(36) NULL');
            }
            if ($this->columnIsNotNullable('admissions', 'school_section_id')) {
                DB::statement('ALTER TABLE admissions MODIFY school_section_id CHAR(36) NULL');
            }
            if ($this->columnIsNotNullable('admissions', 'roll_no')) {
                DB::statement('ALTER TABLE admissions MODIFY roll_no VARCHAR(255) NULL');
            }
        } elseif ($driver === 'pgsql') {
            if ($this->columnIsNotNullable('admissions', 'student_id')) {
                DB::statement('ALTER TABLE admissions ALTER COLUMN student_id DROP NOT NULL');
            }
            if ($this->columnIsNotNullable('admissions', 'school_section_id')) {
                DB::statement('ALTER TABLE admissions ALTER COLUMN school_section_id DROP NOT NULL');
            }
            if ($this->columnIsNotNullable('admissions', 'roll_no')) {
                DB::statement('ALTER TABLE admissions ALTER COLUMN roll_no DROP NOT NULL');
            }
        } else {
            throw new RuntimeException(
                "Unsupported database driver [{$driver}] for admissions nullability evolution."
            );
        }

        if (! $this->foreignKeyExists('admissions', 'student_id')) {
            Schema::table('admissions', function (Blueprint $table) {
                $table->foreign('student_id')->references('id')->on('students')->nullOnDelete();
            });
        }
        if (! $this->foreignKeyExists('admissions', 'school_section_id')) {
            Schema::table('admissions', function (Blueprint $table) {
                $table->foreign('school_section_id')->references('id')->on('school_sections')->nullOnDelete();
            });
        }
    }

    private function rebuildAdmissionsForSqlite(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('admissions_phase1_tmp', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->restrictOnDelete();
            $table->uuid('student_id')->nullable();
            $table->uuid('application_id')->nullable();
            $table->foreignUuid('class_level_id')->constrained('class_levels')->restrictOnDelete();
            $table->uuid('school_section_id')->nullable();
            $table->foreignUuid('academic_session_id')->constrained('academic_sessions')->restrictOnDelete();
            $table->string('roll_no')->nullable();
            $table->string('status')->default('offered');
            $table->timestamp('offered_at')->nullable();
            $table->timestamp('acceptance_deadline')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('configs')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('deleted_at');
            $table->index('school_id');
            $table->index('student_id');
            $table->index('class_level_id');
            $table->index('school_section_id');
            $table->index('academic_session_id');
            $table->index(['school_id', 'status'], 'idx_admissions_school_status');
        });

        $columns = [
            'id', 'school_id', 'student_id', 'class_level_id', 'school_section_id',
            'academic_session_id', 'roll_no', 'status', 'configs', 'created_at', 'updated_at', 'deleted_at',
        ];
        foreach (['application_id', 'offered_at', 'acceptance_deadline', 'accepted_at', 'declined_at', 'expired_at', 'cancelled_at', 'notes'] as $optional) {
            if (Schema::hasColumn('admissions', $optional)) {
                $columns[] = $optional;
            }
        }

        $columnList = implode(', ', $columns);
        DB::statement("INSERT INTO admissions_phase1_tmp ({$columnList}) SELECT {$columnList} FROM admissions");

        Schema::drop('admissions');
        Schema::rename('admissions_phase1_tmp', 'admissions');

        Schema::table('admissions', function (Blueprint $table) {
            $table->foreign('student_id')->references('id')->on('students')->nullOnDelete();
            $table->foreign('school_section_id')->references('id')->on('school_sections')->nullOnDelete();
        });

        if (Schema::hasTable('student_applications') && Schema::hasColumn('admissions', 'application_id')) {
            if (! $this->foreignKeyExists('admissions', 'application_id')) {
                Schema::table('admissions', function (Blueprint $table) {
                    $table->foreign('application_id')->references('id')->on('student_applications')->nullOnDelete();
                });
            }
        }

        Schema::enableForeignKeyConstraints();
    }

    private function columnIsNotNullable(string $table, string $column): bool
    {
        if (! Schema::hasColumn($table, $column)) {
            return false;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $rows = DB::select("PRAGMA table_info('{$table}')");
            foreach ($rows as $row) {
                if (($row->name ?? null) === $column) {
                    return (int) ($row->notnull ?? 0) === 1;
                }
            }

            return false;
        }

        if ($driver === 'mysql') {
            $db = Schema::getConnection()->getDatabaseName();
            $rows = DB::select(
                'SELECT IS_NULLABLE FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ? LIMIT 1',
                [$db, $table, $column]
            );

            return isset($rows[0]) && strtoupper($rows[0]->IS_NULLABLE) === 'NO';
        }

        if ($driver === 'pgsql') {
            $rows = DB::select(
                'SELECT is_nullable FROM information_schema.columns WHERE table_name = ? AND column_name = ? LIMIT 1',
                [$table, $column]
            );

            return isset($rows[0]) && strtoupper($rows[0]->is_nullable) === 'NO';
        }

        throw new RuntimeException("Unsupported driver [{$driver}] for nullability inspection.");
    }

    private function foreignKeyExists(string $table, string $column): bool
    {
        $driver = Schema::getConnection()->getDriverName();
        $connection = Schema::getConnection();

        if ($driver === 'sqlite') {
            $rows = DB::select("PRAGMA foreign_key_list('{$table}')");
            foreach ($rows as $row) {
                if (($row->from ?? null) === $column) {
                    return true;
                }
            }

            return false;
        }

        if ($driver === 'mysql') {
            $db = $connection->getDatabaseName();
            $rows = DB::select(
                'SELECT 1 FROM information_schema.key_column_usage WHERE table_schema = ? AND table_name = ? AND column_name = ? AND referenced_table_name IS NOT NULL LIMIT 1',
                [$db, $table, $column]
            );

            return count($rows) > 0;
        }

        if ($driver === 'pgsql') {
            $rows = DB::select(
                "SELECT 1 FROM information_schema.table_constraints tc
                 JOIN information_schema.key_column_usage kcu ON tc.constraint_name = kcu.constraint_name
                 WHERE tc.table_name = ? AND tc.constraint_type = 'FOREIGN KEY' AND kcu.column_name = ?
                 LIMIT 1",
                [$table, $column]
            );

            return count($rows) > 0;
        }

        return false;
    }

    private function indexExists(string $table, string $index): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $rows = DB::select("PRAGMA index_list('{$table}')");
            foreach ($rows as $row) {
                if (($row->name ?? null) === $index) {
                    return true;
                }
            }

            return false;
        }

        if ($driver === 'mysql') {
            $db = Schema::getConnection()->getDatabaseName();
            $rows = DB::select(
                'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
                [$db, $table, $index]
            );

            return count($rows) > 0;
        }

        if ($driver === 'pgsql') {
            $rows = DB::select(
                'SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ? LIMIT 1',
                [$table, $index]
            );

            return count($rows) > 0;
        }

        return false;
    }

    private function dropForeignIfExists(string $table, string $column): void
    {
        if (! $this->foreignKeyExists($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column) {
            $blueprint->dropForeign([$column]);
        });
    }

    private function dropUniqueIfExists(string $table, string $index): void
    {
        if (! $this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($index) {
            $blueprint->dropUnique($index);
        });
    }
};

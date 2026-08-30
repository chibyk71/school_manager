<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (Schema::hasTable('admissions')) {
            $this->dropForeignSafe('admissions', 'student_id');
            $this->dropForeignSafe('admissions', 'school_section_id');
            $this->dropUniqueSafe('admissions', 'admissions_roll_no_unique');

            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE admissions MODIFY student_id CHAR(36) NULL');
                DB::statement('ALTER TABLE admissions MODIFY school_section_id CHAR(36) NULL');
                DB::statement('ALTER TABLE admissions MODIFY roll_no VARCHAR(255) NULL');
            } elseif ($driver === 'pgsql') {
                DB::statement('ALTER TABLE admissions ALTER COLUMN student_id DROP NOT NULL');
                DB::statement('ALTER TABLE admissions ALTER COLUMN school_section_id DROP NOT NULL');
                DB::statement('ALTER TABLE admissions ALTER COLUMN roll_no DROP NOT NULL');
            }

            Schema::table('admissions', function (Blueprint $table) {
                try {
                    $table->foreign('student_id')->references('id')->on('students')->nullOnDelete();
                } catch (\Throwable $e) {
                }
                try {
                    $table->foreign('school_section_id')->references('id')->on('school_sections')->nullOnDelete();
                } catch (\Throwable $e) {
                }
            });
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

            try {
                $table->index('application_id', 'idx_admissions_application');
            } catch (\Throwable $e) {
            }
        });
    }

    public function down(): void
    {
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

    private function dropForeignSafe(string $table, string $column): void
    {
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropForeign([$column]);
            });
        } catch (\Throwable $e) {
        }
    }

    private function dropUniqueSafe(string $table, string $index): void
    {
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($index) {
                $blueprint->dropUnique($index);
            });
        } catch (\Throwable $e) {
        }
    }
};

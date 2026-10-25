<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3: Term ownership via AcademicSession only; enforce sequence uniqueness.
 *
 * - Verify no contradictory school_id vs session.school_id (including soft-deleted)
 * - Drop terms.school_id
 * - UNIQUE(academic_session_id, ordinal_number) — fail loudly if it cannot be created
 *
 * SQLite: table rebuild must re-apply intended indexes/uniques from the original
 * terms create migration (name unique, session/active, status, ordinal, date range)
 * plus terms_session_ordinal_unique from this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('terms')) {
            return;
        }

        if (Schema::hasColumn('terms', 'school_id') && Schema::hasTable('academic_sessions')) {
            $mismatches = DB::table('terms')
                ->join('academic_sessions', 'terms.academic_session_id', '=', 'academic_sessions.id')
                ->whereColumn('terms.school_id', '!=', 'academic_sessions.school_id')
                ->count();

            if ($mismatches > 0) {
                throw new RuntimeException(
                    "Phase 3 migration aborted: {$mismatches} term row(s) (including soft-deleted) have school_id that does not match parent academic_session.school_id."
                );
            }
        }

        $driver = Schema::getConnection()->getDriverName();

        if (Schema::hasColumn('terms', 'school_id')) {
            if ($driver === 'sqlite') {
                $this->rebuildTermsWithoutSchoolIdForSqlite();
            } else {
                try {
                    Schema::table('terms', function (Blueprint $table) {
                        $table->dropForeign(['school_id']);
                    });
                } catch (\Throwable $e) {
                    // FK may already be absent
                }

                Schema::table('terms', function (Blueprint $table) {
                    $table->dropColumn('school_id');
                });
            }
        }

        $this->ensureSessionOrdinalUnique();
    }

    protected function ensureSessionOrdinalUnique(): void
    {
        try {
            Schema::table('terms', function (Blueprint $table) {
                $table->unique(['academic_session_id', 'ordinal_number'], 'terms_session_ordinal_unique');
            });
        } catch (\Throwable $e) {
            $msg = strtolower($e->getMessage());
            $already = str_contains($msg, 'already exists')
                || str_contains($msg, 'duplicate')
                || str_contains($msg, 'exists');

            if (! $already) {
                throw new RuntimeException(
                    'Phase 3 migration aborted: failed to create UNIQUE(academic_session_id, ordinal_number): '.$e->getMessage(),
                    0,
                    $e
                );
            }
        }
    }

    protected function rebuildTermsWithoutSchoolIdForSqlite(): void
    {
        // Leftover from a previous interrupted rebuild can break later DELETE FROM schools
        // (SQLite FK bookkeeping references main.terms_old_phase3).
        try {
            DB::statement('PRAGMA foreign_keys = OFF');
        } catch (\Throwable) {
        }
        Schema::dropIfExists('terms_old_phase3');
        try {
            DB::statement('PRAGMA foreign_keys = ON');
        } catch (\Throwable) {
        }

        foreach ([
            'terms_session_name_unique',
            'terms_session_active_idx',
            'terms_status_idx',
            'terms_ordinal_idx',
            'terms_date_range_idx',
            'terms_session_ordinal_unique',
        ] as $index) {
            try {
                Schema::table('terms', function (Blueprint $table) use ($index) {
                    $table->dropIndex($index);
                });
            } catch (\Throwable) {
                // optional / may not exist on partial schemas
            }
        }

        Schema::rename('terms', 'terms_old_phase3');

        $oldCols = Schema::getColumnListing('terms_old_phase3');
        $has = static function (string $c) use ($oldCols): bool {
            return in_array($c, $oldCols, true);
        };

        Schema::create('terms', function (Blueprint $table) use ($has) {
            $table->uuid('id')->primary();
            $table->uuid('academic_session_id');
            $table->string('name', 60);
            $table->string('short_name', 10)->nullable();
            $table->unsignedTinyInteger('ordinal_number');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            if ($has('state')) {
                $table->string('state')->nullable();
            }
            if ($has('status')) {
                $table->string('status', 20)->default('pending');
            }
            if ($has('is_active')) {
                $table->boolean('is_active')->default(false);
            }
            if ($has('is_closed')) {
                $table->boolean('is_closed')->default(false);
            }
            if ($has('closed_at')) {
                $table->timestamp('closed_at')->nullable();
            }
            if ($has('color')) {
                $table->string('color', 20)->nullable();
            }
            if ($has('options')) {
                $table->json('options')->nullable();
            }
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('academic_session_id')
                ->references('id')
                ->on('academic_sessions')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });

        $newCols = Schema::getColumnListing('terms');
        $cols = array_values(array_intersect(
            array_values(array_filter($oldCols, static fn ($c) => $c !== 'school_id')),
            $newCols
        ));
        if ($cols !== []) {
            $list = implode(', ', array_map(static fn ($c) => '"'.$c.'"', $cols));
            DB::statement("INSERT INTO terms ({$list}) SELECT {$list} FROM terms_old_phase3");
        }

        try {
            DB::statement('PRAGMA foreign_keys = OFF');
        } catch (\Throwable) {
        }
        Schema::dropIfExists('terms_old_phase3');
        try {
            DB::statement('PRAGMA foreign_keys = ON');
        } catch (\Throwable) {
        }

        // Restore indexes from the original create_terms migration.
        // terms_session_ordinal_unique is applied by ensureSessionOrdinalUnique() after rebuild.
        $this->restoreTermsIndexesAfterSqliteRebuild();
    }

    protected function restoreTermsIndexesAfterSqliteRebuild(): void
    {
        $cols = Schema::getColumnListing('terms');
        $has = static fn (string $c): bool => in_array($c, $cols, true);

        Schema::table('terms', function (Blueprint $table) use ($has) {
            if ($has('academic_session_id') && $has('name')) {
                $table->unique(['academic_session_id', 'name'], 'terms_session_name_unique');
            }
            if ($has('academic_session_id') && $has('is_active')) {
                $table->index(['academic_session_id', 'is_active'], 'terms_session_active_idx');
            }
            if ($has('status')) {
                $table->index('status', 'terms_status_idx');
            }
            if ($has('ordinal_number')) {
                $table->index('ordinal_number', 'terms_ordinal_idx');
            }
            if ($has('start_date') && $has('end_date')) {
                $table->index(['start_date', 'end_date'], 'terms_date_range_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('terms')) {
            return;
        }

        try {
            Schema::table('terms', function (Blueprint $table) {
                $table->dropUnique('terms_session_ordinal_unique');
            });
        } catch (\Throwable) {
        }

        if (! Schema::hasColumn('terms', 'school_id')) {
            Schema::table('terms', function (Blueprint $table) {
                $table->uuid('school_id')->nullable()->after('id');
            });

            if (Schema::hasTable('academic_sessions')) {
                $terms = DB::table('terms')->whereNull('school_id')->get(['id', 'academic_session_id']);
                foreach ($terms as $t) {
                    $schoolId = DB::table('academic_sessions')->where('id', $t->academic_session_id)->value('school_id');
                    if ($schoolId) {
                        DB::table('terms')->where('id', $t->id)->update(['school_id' => $schoolId]);
                    }
                }
            }
        }
    }
};

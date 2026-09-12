<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3: Term ownership via AcademicSession only; enforce sequence uniqueness.
 *
 * - Verify no contradictory school_id vs session.school_id rows before drop
 * - Drop terms.school_id
 * - UNIQUE(academic_session_id, ordinal_number)
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
                ->whereNull('terms.deleted_at')
                ->count();

            if ($mismatches > 0) {
                throw new RuntimeException(
                    "Phase 3 migration aborted: {$mismatches} term row(s) have school_id that does not match parent academic_session.school_id."
                );
            }
        }

        $driver = Schema::getConnection()->getDriverName();

        if (Schema::hasColumn('terms', 'school_id')) {
            if ($driver !== 'sqlite') {
                try {
                    Schema::table('terms', function (Blueprint $table) {
                        $table->dropForeign(['school_id']);
                    });
                } catch (\Throwable) {
                }
            }

            Schema::table('terms', function (Blueprint $table) {
                $table->dropColumn('school_id');
            });
        }

        $indexExists = false;
        try {
            if ($driver === 'sqlite') {
                $indexes = Schema::getConnection()->select("PRAGMA index_list('terms')");
                foreach ($indexes as $idx) {
                    if (str_contains(strtolower($idx->name ?? ''), 'ordinal')) {
                        $indexExists = true;
                        break;
                    }
                }
            }
        } catch (\Throwable) {
        }

        if (! $indexExists) {
            try {
                Schema::table('terms', function (Blueprint $table) {
                    $table->unique(['academic_session_id', 'ordinal_number'], 'terms_session_ordinal_unique');
                });
            } catch (\Throwable) {
            }
        }
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

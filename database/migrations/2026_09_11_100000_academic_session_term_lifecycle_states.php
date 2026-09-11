<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1: Academic Session & Term authoritative lifecycle states.
 *
 * - Adds `state` columns (Spatie Model States short names)
 * - Deterministically reconciles legacy status / is_current / is_active / is_closed
 * - Fails loudly on contradictory legacy combinations
 * - Removes obsolete lifecycle columns and indexes
 * - Preserves closed_at (audit) and activated_at
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Academic Sessions ──────────────────────────────────────────
        Schema::table('academic_sessions', function (Blueprint $table) {
            $table->string('state', 20)
                ->nullable()
                ->after('status')
                ->comment('Authoritative lifecycle: draft, planned, active, paused, closed');
        });

        $this->reconcileAcademicSessions();

        Schema::table('academic_sessions', function (Blueprint $table) {
            $table->string('state', 20)->nullable(false)->default('draft')->change();
        });

        Schema::table('academic_sessions', function (Blueprint $table) {
            $table->dropIndex('academic_sessions_school_current_idx');
            $table->dropIndex('academic_sessions_status_idx');
        });

        Schema::table('academic_sessions', function (Blueprint $table) {
            $table->dropColumn(['is_current', 'status']);
        });

        Schema::table('academic_sessions', function (Blueprint $table) {
            $table->index('state', 'academic_sessions_state_idx');
            $table->index(['school_id', 'state'], 'academic_sessions_school_state_idx');
        });

        // ── Terms ──────────────────────────────────────────────────────
        Schema::table('terms', function (Blueprint $table) {
            $table->string('state', 20)
                ->nullable()
                ->after('status')
                ->comment('Authoritative lifecycle: planned, active, closed');
        });

        $this->reconcileTerms();

        Schema::table('terms', function (Blueprint $table) {
            $table->string('state', 20)->nullable(false)->default('planned')->change();
        });

        Schema::table('terms', function (Blueprint $table) {
            $table->dropIndex('terms_session_active_idx');
            $table->dropIndex('terms_status_idx');
        });

        Schema::table('terms', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'is_closed', 'status']);
        });

        Schema::table('terms', function (Blueprint $table) {
            $table->index('state', 'terms_state_idx');
            $table->index(['academic_session_id', 'state'], 'terms_session_state_idx');
        });
    }

    public function down(): void
    {
        Schema::table('terms', function (Blueprint $table) {
            $table->dropIndex('terms_state_idx');
            $table->dropIndex('terms_session_state_idx');
        });

        Schema::table('terms', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->after('end_date');
            $table->boolean('is_active')->default(false)->after('status');
            $table->boolean('is_closed')->default(false)->after('is_active');
        });

        DB::table('terms')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $map = match ($row->state) {
                    'planned' => ['status' => 'pending', 'is_active' => false, 'is_closed' => false],
                    'active'  => ['status' => 'active',  'is_active' => true,  'is_closed' => false],
                    'closed'  => ['status' => 'closed',  'is_active' => false, 'is_closed' => true],
                    default   => ['status' => 'pending', 'is_active' => false, 'is_closed' => false],
                };
                DB::table('terms')->where('id', $row->id)->update($map);
            }
        });

        Schema::table('terms', function (Blueprint $table) {
            $table->dropColumn('state');
            $table->index(['academic_session_id', 'is_active'], 'terms_session_active_idx');
            $table->index('status', 'terms_status_idx');
        });

        Schema::table('academic_sessions', function (Blueprint $table) {
            $table->dropIndex('academic_sessions_state_idx');
            $table->dropIndex('academic_sessions_school_state_idx');
        });

        Schema::table('academic_sessions', function (Blueprint $table) {
            $table->boolean('is_current')->default(false)->after('end_date');
            $table->string('status', 20)->default('draft')->after('is_current');
        });

        DB::table('academic_sessions')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $map = match ($row->state) {
                    'draft'   => ['status' => 'draft',    'is_current' => false],
                    'planned' => ['status' => 'upcoming', 'is_current' => false],
                    'active'  => ['status' => 'active',   'is_current' => true],
                    'paused'  => ['status' => 'active',   'is_current' => false],
                    'closed'  => ['status' => 'closed',   'is_current' => false],
                    default   => ['status' => 'draft',    'is_current' => false],
                };
                DB::table('academic_sessions')->where('id', $row->id)->update($map);
            }
        });

        Schema::table('academic_sessions', function (Blueprint $table) {
            $table->dropColumn('state');
            $table->index(['school_id', 'is_current'], 'academic_sessions_school_current_idx');
            $table->index('status', 'academic_sessions_status_idx');
        });
    }

    private function reconcileAcademicSessions(): void
    {
        $rows = DB::table('academic_sessions')->select('id', 'school_id', 'name', 'status', 'is_current')->get();

        $contradictions = [];

        foreach ($rows as $row) {
            $status = strtolower((string) $row->status);
            $isCurrent = (bool) $row->is_current;

            $state = match (true) {
                $status === 'draft' && ! $isCurrent => 'draft',
                $status === 'upcoming' && ! $isCurrent => 'planned',
                $status === 'active' && $isCurrent => 'active',
                $status === 'closed' && ! $isCurrent => 'closed',
                $status === 'archived' && ! $isCurrent => 'closed',
                default => null,
            };

            if ($state === null) {
                $contradictions[] = sprintf(
                    'id=%s school_id=%s name=%s status=%s is_current=%s',
                    $row->id,
                    $row->school_id,
                    $row->name,
                    $row->status,
                    $isCurrent ? 'true' : 'false'
                );
                continue;
            }

            DB::table('academic_sessions')->where('id', $row->id)->update(['state' => $state]);
        }

        if ($contradictions !== []) {
            throw new \RuntimeException(
                "Academic Session lifecycle migration failed: contradictory legacy records:\n" .
                implode("\n", $contradictions)
            );
        }
    }

    private function reconcileTerms(): void
    {
        $rows = DB::table('terms')->select('id', 'academic_session_id', 'name', 'status', 'is_active', 'is_closed')->get();

        $contradictions = [];

        foreach ($rows as $row) {
            $status = strtolower((string) $row->status);
            $isActive = (bool) $row->is_active;
            $isClosed = (bool) $row->is_closed;

            $state = match (true) {
                ($status === 'pending' || $status === 'planned') && ! $isActive && ! $isClosed => 'planned',
                $status === 'active' && $isActive && ! $isClosed => 'active',
                $status === 'closed' && ! $isActive && $isClosed => 'closed',
                default => null,
            };

            if ($state === null) {
                $contradictions[] = sprintf(
                    'id=%s session=%s name=%s status=%s is_active=%s is_closed=%s',
                    $row->id,
                    $row->academic_session_id,
                    $row->name,
                    $row->status,
                    $isActive ? 'true' : 'false',
                    $isClosed ? 'true' : 'false'
                );
                continue;
            }

            DB::table('terms')->where('id', $row->id)->update(['state' => $state]);
        }

        if ($contradictions !== []) {
            throw new \RuntimeException(
                "Term lifecycle migration failed: contradictory legacy records:\n" .
                implode("\n", $contradictions)
            );
        }
    }
};

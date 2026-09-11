<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(Tests\TestCase::class);

beforeEach(function () {
    Schema::dropIfExists('terms');
    Schema::dropIfExists('academic_sessions');
});

afterEach(function () {
    Schema::dropIfExists('terms');
    Schema::dropIfExists('academic_sessions');
});

function buildLegacyAcademicTables(): void
{
    Schema::create('academic_sessions', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('school_id');
        $table->string('name');
        $table->date('start_date')->nullable();
        $table->date('end_date')->nullable();
        $table->boolean('is_current')->default(false);
        $table->string('status', 20)->default('draft');
        $table->timestamp('activated_at')->nullable();
        $table->timestamp('closed_at')->nullable();
        $table->timestamps();
        $table->softDeletes();
        $table->index(['school_id', 'is_current'], 'academic_sessions_school_current_idx');
        $table->index('status', 'academic_sessions_status_idx');
    });

    Schema::create('terms', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('school_id');
        $table->uuid('academic_session_id');
        $table->string('name');
        $table->unsignedInteger('ordinal_number')->default(1);
        $table->date('start_date')->nullable();
        $table->date('end_date')->nullable();
        $table->string('status', 20)->default('pending');
        $table->boolean('is_active')->default(false);
        $table->boolean('is_closed')->default(false);
        $table->timestamp('closed_at')->nullable();
        $table->timestamps();
        $table->softDeletes();
        $table->index(['academic_session_id', 'is_active'], 'terms_session_active_idx');
        $table->index('status', 'terms_status_idx');
    });
}

function runLifecycleMigration(): void
{
    $migration = require base_path('database/migrations/2026_09_11_100000_academic_session_term_lifecycle_states.php');
    $migration->up();
}

it('migrates session legacy combinations to authoritative state', function () {
    buildLegacyAcademicTables();

    $rows = [
        ['status' => 'draft', 'is_current' => false, 'expect' => 'draft'],
        ['status' => 'upcoming', 'is_current' => false, 'expect' => 'planned'],
        ['status' => 'active', 'is_current' => true, 'expect' => 'active'],
        ['status' => 'closed', 'is_current' => false, 'expect' => 'closed'],
        ['status' => 'archived', 'is_current' => false, 'expect' => 'closed'],
    ];

    foreach ($rows as $i => $row) {
        DB::table('academic_sessions')->insert([
            'id' => (string) Str::uuid(),
            'school_id' => (string) Str::uuid(),
            'name' => "Session-{$i}",
            'start_date' => '2025-09-01',
            'end_date' => '2026-07-31',
            'is_current' => $row['is_current'],
            'status' => $row['status'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // Empty terms table still required for migration terms stage
    runLifecycleMigration();

    expect(Schema::hasColumn('academic_sessions', 'state'))->toBeTrue()
        ->and(Schema::hasColumn('academic_sessions', 'is_current'))->toBeFalse()
        ->and(Schema::hasColumn('academic_sessions', 'status'))->toBeFalse();

    $mapped = DB::table('academic_sessions')->orderBy('name')->pluck('state', 'name');
    foreach ($rows as $i => $row) {
        expect($mapped["Session-{$i}"])->toBe($row['expect']);
    }
});

it('migrates term legacy combinations to authoritative state', function () {
    buildLegacyAcademicTables();

    $sessionId = (string) Str::uuid();
    DB::table('academic_sessions')->insert([
        'id' => $sessionId,
        'school_id' => (string) Str::uuid(),
        'name' => '2025/2026',
        'start_date' => '2025-09-01',
        'end_date' => '2026-07-31',
        'is_current' => true,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $termRows = [
        ['status' => 'pending', 'is_active' => false, 'is_closed' => false, 'expect' => 'planned'],
        ['status' => 'planned', 'is_active' => false, 'is_closed' => false, 'expect' => 'planned'],
        ['status' => 'active', 'is_active' => true, 'is_closed' => false, 'expect' => 'active'],
        ['status' => 'closed', 'is_active' => false, 'is_closed' => true, 'expect' => 'closed'],
    ];

    foreach ($termRows as $i => $row) {
        DB::table('terms')->insert([
            'id' => (string) Str::uuid(),
            'school_id' => (string) Str::uuid(),
            'academic_session_id' => $sessionId,
            'name' => "Term-{$i}",
            'ordinal_number' => $i + 1,
            'status' => $row['status'],
            'is_active' => $row['is_active'],
            'is_closed' => $row['is_closed'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    runLifecycleMigration();

    expect(Schema::hasColumn('terms', 'state'))->toBeTrue()
        ->and(Schema::hasColumn('terms', 'is_active'))->toBeFalse()
        ->and(Schema::hasColumn('terms', 'is_closed'))->toBeFalse()
        ->and(Schema::hasColumn('terms', 'status'))->toBeFalse();

    $mapped = DB::table('terms')->orderBy('name')->pluck('state', 'name');
    foreach ($termRows as $i => $row) {
        expect($mapped["Term-{$i}"])->toBe($row['expect']);
    }
});

it('fails before destructive changes when session data is contradictory', function () {
    buildLegacyAcademicTables();

    DB::table('academic_sessions')->insert([
        'id' => (string) Str::uuid(),
        'school_id' => (string) Str::uuid(),
        'name' => 'Bad Session',
        'start_date' => '2025-09-01',
        'end_date' => '2026-07-31',
        'is_current' => false, // contradict: active without current
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => runLifecycleMigration())->toThrow(RuntimeException::class);

    // Stage-1 validation throws before schema mutation
    expect(Schema::hasColumn('academic_sessions', 'state'))->toBeFalse()
        ->and(Schema::hasColumn('academic_sessions', 'is_current'))->toBeTrue()
        ->and(Schema::hasColumn('academic_sessions', 'status'))->toBeTrue();
});

it('fails before destructive changes when term data is contradictory', function () {
    buildLegacyAcademicTables();

    $sessionId = (string) Str::uuid();
    DB::table('academic_sessions')->insert([
        'id' => $sessionId,
        'school_id' => (string) Str::uuid(),
        'name' => '2025/2026',
        'is_current' => true,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('terms')->insert([
        'id' => (string) Str::uuid(),
        'school_id' => (string) Str::uuid(),
        'academic_session_id' => $sessionId,
        'name' => 'Bad Term',
        'ordinal_number' => 1,
        'status' => 'active',
        'is_active' => false, // contradict
        'is_closed' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => runLifecycleMigration())->toThrow(RuntimeException::class);

    expect(Schema::hasColumn('terms', 'state'))->toBeFalse()
        ->and(Schema::hasColumn('terms', 'is_active'))->toBeTrue()
        ->and(Schema::hasColumn('terms', 'status'))->toBeTrue();
});

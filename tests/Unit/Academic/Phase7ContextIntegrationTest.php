<?php

/**
 * Phase 7: authoritative Academic context wiring after legacy API removal.
 */

use App\Facades\Academic;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\Term;
use App\Models\School;
use App\Services\AcademicSessionService;
use App\States\Academic\AcademicSession\Active as SessionActive;
use App\States\Academic\AcademicSession\Paused as SessionPaused;
use App\States\Academic\Term\Active as TermActive;
use App\States\Academic\Term\Planned as TermPlanned;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(Tests\TestCase::class);

beforeEach(function () {
    Schema::dropIfExists('activity_log');
    Schema::dropIfExists('terms');
    Schema::dropIfExists('academic_sessions');
    Schema::dropIfExists('schools');

    Schema::create('schools', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name')->nullable();
        $table->string('slug')->nullable();
        $table->json('data')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('academic_sessions', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('school_id');
        $table->string('name');
        $table->date('start_date')->nullable();
        $table->date('end_date')->nullable();
        $table->string('state')->default('draft');
        $table->timestamp('activated_at')->nullable();
        $table->timestamp('closed_at')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('terms', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('academic_session_id');
        $table->string('name');
        $table->string('short_name')->nullable();
        $table->unsignedInteger('ordinal_number')->default(1);
        $table->text('description')->nullable();
        $table->date('start_date')->nullable();
        $table->date('end_date')->nullable();
        $table->string('state')->default(TermPlanned::$name);
        $table->timestamp('closed_at')->nullable();
        $table->string('color')->nullable();
        $table->json('options')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('activity_log', function (Blueprint $table) {
        $table->bigIncrements('id');
        $table->string('log_name')->nullable();
        $table->text('description');
        $table->nullableUuidMorphs('subject', 'subject');
        $table->nullableUuidMorphs('causer', 'causer');
        $table->json('properties')->nullable();
        $table->uuid('batch_uuid')->nullable();
        $table->string('event')->nullable();
        $table->timestamps();
    });

    $schoolId = (string) Str::uuid();
    $this->school = new School();
    $this->school->forceFill(['id' => $schoolId, 'name' => 'Phase7 School', 'slug' => 'p7']);
    $this->school->exists = true;

    DB::table('schools')->insert([
        'id' => $schoolId,
        'name' => 'Phase7 School',
        'slug' => 'p7',
        'data' => json_encode(['id' => $schoolId]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->app->instance('schoolManager', new class($this->school)
    {
        public function __construct(private School $school) {}

        public function getActiveSchool(): School
        {
            return $this->school;
        }
    });

    $this->app->forgetInstance('academicContext');
    $this->app->singleton('academicContext', fn () => new AcademicSessionService());
    Cache::flush();
});

it('Academic facade is the path for current context after Phase 7', function () {
    $sid = (string) Str::uuid();
    DB::table('academic_sessions')->insert([
        'id' => $sid,
        'school_id' => $this->school->id,
        'name' => '2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'state' => SessionActive::$name,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $tid = (string) Str::uuid();
    DB::table('terms')->insert([
        'id' => $tid,
        'academic_session_id' => $sid,
        'name' => 'T1',
        'ordinal_number' => 1,
        'start_date' => '2026-01-01',
        'end_date' => '2026-04-30',
        'state' => TermActive::$name,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(Academic::currentSession()?->id)->toBe($sid)
        ->and(Academic::currentTerm()?->id)->toBe($tid)
        ->and(file_exists(base_path('app/Services/AcademicCalendarService.php')))->toBeFalse()
        // autoload=false avoids fatal include when Composer classmap/PSR-4 still points at the deleted file
        ->and(class_exists(\App\Services\AcademicCalendarService::class, false))->toBeFalse();
});

it('sessionBelongsToSchool validates ownership without implying current context', function () {
    $own = (string) Str::uuid();
    $otherSchool = (string) Str::uuid();
    DB::table('schools')->insert([
        'id' => $otherSchool,
        'name' => 'Other',
        'slug' => 'other',
        'data' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('academic_sessions')->insert([
        'id' => $own,
        'school_id' => $this->school->id,
        'name' => 'Own',
        'state' => SessionPaused::$name,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $foreign = (string) Str::uuid();
    DB::table('academic_sessions')->insert([
        'id' => $foreign,
        'school_id' => $otherSchool,
        'name' => 'Foreign',
        'state' => SessionActive::$name,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $svc = app(AcademicSessionService::class);
    expect($svc->sessionBelongsToSchool($this->school, $own))->toBeTrue()
        ->and($svc->sessionBelongsToSchool($this->school, $foreign))->toBeFalse();
});

it('legacy global currentSession/currentTerm helpers are removed', function () {
    expect(function_exists('currentSession'))->toBeFalse()
        ->and(function_exists('currentTerm'))->toBeFalse();
});

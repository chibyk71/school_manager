<?php

use App\Models\Academic\AcademicSession;
use App\Models\School;
use App\Models\Student\StudentApplication;
use App\Models\User;
use App\Services\Student\StudentApplicationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Model::unguard();

    Schema::dropIfExists('student_applications');
    Schema::dropIfExists('academic_sessions');
    Schema::dropIfExists('schools');
    Schema::dropIfExists('users');

    Schema::create('schools', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('code')->nullable();
        $table->timestamps();
    });

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->timestamps();
    });

    Schema::create('academic_sessions', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('school_id');
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('student_applications', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('school_id');
        $table->uuid('academic_session_id')->nullable();
        $table->uuid('school_section_id')->nullable();
        $table->uuid('class_level_id')->nullable();
        $table->string('first_name', 100);
        $table->string('last_name', 100);
        $table->string('middle_name', 100)->nullable();
        $table->date('date_of_birth')->nullable();
        $table->string('gender', 30)->nullable();
        $table->string('phone', 30)->nullable();
        $table->string('email', 191)->nullable();
        $table->string('nationality', 100)->nullable();
        $table->string('state_of_origin', 100)->nullable();
        $table->string('religion', 50)->nullable();
        $table->string('blood_group', 10)->nullable();
        $table->string('previous_school', 255)->nullable();
        $table->string('previous_class', 100)->nullable();
        $table->string('previous_school_address', 500)->nullable();
        $table->json('guardians_data')->nullable();
        $table->string('source', 30)->default('public_portal');
        $table->string('status', 30)->default('submitted');
        $table->string('application_number', 50)->nullable();
        $table->string('application_token', 100)->nullable()->unique();
        $table->unsignedBigInteger('reviewed_by')->nullable();
        $table->timestamp('submitted_at')->nullable();
        $table->timestamp('reviewed_at')->nullable();
        $table->text('rejection_reason')->nullable();
        $table->text('admin_notes')->nullable();
        $table->uuid('student_id')->nullable();
        $table->json('documents')->nullable();
        $table->json('custom_data')->nullable();
        $table->timestamps();
        $table->softDeletes();
        $table->unique(['school_id', 'application_number']);
    });
});

function makeSchool(string $name = 'School A'): School
{
    $school = new School();
    $school->forceFill([
        'id' => (string) Str::uuid(),
        'name' => $name,
        'code' => strtoupper(substr($name, 0, 3)),
    ]);
    $school->save();

    return $school;
}

function makeSession(School $school, string $name = '2026/2027'): AcademicSession
{
    $session = new AcademicSession();
    $session->forceFill([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'name' => $name,
    ]);
    $session->save();

    return $session;
}

function makeUser(): User
{
    $user = new User();
    $user->forceFill(['name' => 'Reviewer', 'email' => 'rev@example.com']);
    $user->save();

    return $user;
}

it('creates public application without profile user or student', function () {
    $school = makeSchool();
    $session = makeSession($school);
    $service = app(StudentApplicationService::class);

    $app = $service->submitPublicApplication([
        'academic_session_id' => $session->id,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'date_of_birth' => '2015-05-01',
        'email' => 'parent@example.com',
        'guardians_data' => [['name' => 'Parent', 'phone' => '080', 'relationship' => 'mother']],
    ], $school);

    expect($app->school_id)->toBe($school->id)
        ->and($app->student_id)->toBeNull()
        ->and($app->status)->toBe(StudentApplication::STATUS_SUBMITTED)
        ->and($app->source)->toBe(StudentApplication::SOURCE_PUBLIC)
        ->and($app->application_number)->not->toBeEmpty()
        ->and($app->application_token)->toHaveLength(64);
});

it('creates staff application with same domain entity', function () {
    $school = makeSchool();
    $session = makeSession($school);
    $staff = makeUser();
    $service = app(StudentApplicationService::class);

    $app = $service->submitStaffApplication([
        'academic_session_id' => $session->id,
        'first_name' => 'Grace',
        'last_name' => 'Hopper',
    ], $school, $staff);

    expect($app->source)->toBe(StudentApplication::SOURCE_STAFF)
        ->and($app->status)->toBe(StudentApplication::STATUS_SUBMITTED);
});

it('rejects cross-school academic session', function () {
    $schoolA = makeSchool('Alpha');
    $schoolB = makeSchool('Beta');
    $sessionB = makeSession($schoolB);

    $app = new StudentApplication();
    $app->fill([
        'school_id' => $schoolA->id,
        'academic_session_id' => $sessionB->id,
        'first_name' => 'X',
        'last_name' => 'Y',
        'status' => 'submitted',
    ]);

    expect(fn () => $app->save())->toThrow(ValidationException::class);
});

it('generates stable application number', function () {
    $school = makeSchool();
    $app = new StudentApplication();
    $app->fill([
        'school_id' => $school->id,
        'first_name' => 'A',
        'last_name' => 'B',
        'status' => 'submitted',
    ]);
    $n1 = $app->assignApplicationNumber($school);
    $n2 = $app->assignApplicationNumber($school);
    expect($n1)->toBe($n2)->and($n1)->not->toBeEmpty();
});

it('allows valid status transitions and blocks invalid ones', function () {
    $school = makeSchool();
    $app = StudentApplication::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'first_name' => 'A',
        'last_name' => 'B',
        'status' => StudentApplication::STATUS_SUBMITTED,
    ]);

    expect($app->canTransitionTo(StudentApplication::STATUS_UNDER_REVIEW))->toBeTrue();
    $app->transitionTo(StudentApplication::STATUS_UNDER_REVIEW);
    $app->save();

    expect($app->canTransitionTo(StudentApplication::STATUS_APPROVED))->toBeTrue();
    expect($app->canTransitionTo(StudentApplication::STATUS_SUBMITTED))->toBeFalse();

    $app->transitionTo(StudentApplication::STATUS_APPROVED);
    $app->save();
    expect($app->canTransitionTo(StudentApplication::STATUS_UNDER_REVIEW))->toBeFalse();
});

it('approves without creating student and records reviewer', function () {
    $school = makeSchool();
    $reviewer = makeUser();
    $service = app(StudentApplicationService::class);

    $app = StudentApplication::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'first_name' => 'A',
        'last_name' => 'B',
        'status' => StudentApplication::STATUS_SUBMITTED,
        'application_number' => 'APP-TEST-1',
        'application_token' => Str::random(64),
    ]);

    $approved = $service->approveApplication($app, $reviewer, 'Looks good');

    expect($approved->status)->toBe(StudentApplication::STATUS_APPROVED)
        ->and($approved->reviewed_by)->toBe($reviewer->id)
        ->and($approved->reviewed_at)->not->toBeNull()
        ->and($approved->student_id)->toBeNull();
});

it('rejects with reason and records reviewer', function () {
    $school = makeSchool();
    $reviewer = makeUser();
    $service = app(StudentApplicationService::class);

    $app = StudentApplication::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'first_name' => 'A',
        'last_name' => 'B',
        'status' => StudentApplication::STATUS_UNDER_REVIEW,
        'application_number' => 'APP-TEST-2',
        'application_token' => Str::random(64),
    ]);

    $rejected = $service->rejectApplication($app, 'Incomplete documents', $reviewer);

    expect($rejected->status)->toBe(StudentApplication::STATUS_REJECTED)
        ->and($rejected->rejection_reason)->toBe('Incomplete documents')
        ->and($rejected->reviewed_by)->toBe($reviewer->id);
});

it('detects likely duplicates within school session as warning only', function () {
    $school = makeSchool();
    $session = makeSession($school);

    $first = StudentApplication::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'date_of_birth' => '2015-05-01',
        'email' => 'same@example.com',
        'status' => 'submitted',
        'application_number' => 'APP-D1',
        'application_token' => Str::random(64),
    ]);

    $second = new StudentApplication([
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'date_of_birth' => '2015-05-01',
        'email' => 'same@example.com',
        'status' => 'submitted',
    ]);

    $dupes = $second->findLikelyDuplicates();
    expect($dupes->count())->toBeGreaterThan(0)
        ->and($dupes->first()->id)->toBe($first->id);

    // Multiple applications remain possible
    $second->application_number = 'APP-D2';
    $second->application_token = Str::random(64);
    $second->id = (string) Str::uuid();
    $second->save();
    expect(StudentApplication::query()->count())->toBe(2);
});

it('does not treat other school matches as duplicates', function () {
    $schoolA = makeSchool('Alpha');
    $schoolB = makeSchool('Beta');
    $sessionA = makeSession($schoolA);
    $sessionB = makeSession($schoolB);

    StudentApplication::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $schoolA->id,
        'academic_session_id' => $sessionA->id,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'date_of_birth' => '2015-05-01',
        'status' => 'submitted',
        'application_number' => 'APP-A1',
        'application_token' => Str::random(64),
    ]);

    $candidate = new StudentApplication([
        'school_id' => $schoolB->id,
        'academic_session_id' => $sessionB->id,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'date_of_birth' => '2015-05-01',
        'status' => 'submitted',
    ]);

    expect($candidate->findLikelyDuplicates())->toHaveCount(0);
});

it('maps legacy pending status to submitted', function () {
    $school = makeSchool();
    $app = StudentApplication::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'first_name' => 'A',
        'last_name' => 'B',
        'status' => 'pending',
        'application_number' => 'APP-LEG',
        'application_token' => Str::random(64),
    ]);

    expect($app->fresh()->status)->toBe(StudentApplication::STATUS_SUBMITTED)
        ->and($app->fresh()->canonical_status)->toBe(StudentApplication::STATUS_SUBMITTED);
});

it('application fee config does not invent a ledger', function () {
    $service = app(StudentApplicationService::class);
    $config = $service->applicationFeeConfig(null);

    expect($config)->toHaveKeys(['required', 'amount', 'fee_type']);
});

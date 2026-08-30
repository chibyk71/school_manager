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
    Schema::dropIfExists('school_sections');
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

    Schema::create('school_sections', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('school_id');
        $table->string('name')->nullable();
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
        $table->string('fee_payment_status', 30)->default('not_required');
        $table->string('fee_payment_reference', 191)->nullable();
        $table->timestamp('fee_paid_at')->nullable();
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

it('rejects cross-school school_section_id even if legacy column is set', function () {
    $schoolA = makeSchool('Alpha');
    $schoolB = makeSchool('Beta');

    $sectionBId = (string) Str::uuid();
    \DB::table('school_sections')->insert([
        'id' => $sectionBId,
        'school_id' => $schoolB->id,
        'name' => 'Foreign Section',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $app = new StudentApplication();
    $app->fill([
        'school_id' => $schoolA->id,
        'first_name' => 'X',
        'last_name' => 'Y',
        'status' => 'submitted',
    ]);
    $app->school_section_id = $sectionBId;

    expect(fn () => $app->save())->toThrow(ValidationException::class);
});

it('strips school_section_id from Phase 2 submission sanitize path', function () {
    $school = makeSchool();
    $session = makeSession($school);
    $service = app(StudentApplicationService::class);

    $app = $service->submitPublicApplication([
        'academic_session_id' => $session->id,
        'first_name' => 'Strip',
        'last_name' => 'Section',
        'school_section_id' => (string) Str::uuid(),
    ], $school);

    expect($app->school_section_id)->toBeNull();
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

it('public resource omits staff and internal fields', function () {
    $school = makeSchool();
    $session = makeSession($school);

    $app = StudentApplication::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'academic_session_id' => $session->id,
        'first_name' => 'Pub',
        'last_name' => 'Lic',
        'status' => StudentApplication::STATUS_UNDER_REVIEW,
        'application_number' => 'APP-PUB-1',
        'application_token' => Str::random(64),
        'admin_notes' => 'INTERNAL SECRET NOTES',
        'fee_payment_status' => StudentApplication::FEE_UNPAID,
        'fee_payment_reference' => 'PAY-SECRET-REF',
        'guardians_data' => [['name' => 'G', 'phone' => '1', 'relationship' => 'mother']],
        'documents' => [['type' => 'birth_cert', 'path' => '/secret']],
        'custom_data' => ['internal' => true],
    ]);
    $app->load('academicSession');

    $resource = (new \App\Http\Resources\Student\PublicStudentApplicationResource($app))->resolve();

    expect($resource)->toHaveKeys([
        'application_number',
        'full_name',
        'status',
        'status_label',
        'fee_payment_status',
        'fee_satisfied',
    ])
        ->and($resource)->not->toHaveKey('admin_notes')
        ->and($resource)->not->toHaveKey('reviewed_by')
        ->and($resource)->not->toHaveKey('fee_payment_reference')
        ->and($resource)->not->toHaveKey('student_id')
        ->and($resource)->not->toHaveKey('documents')
        ->and($resource)->not->toHaveKey('guardians_data')
        ->and($resource)->not->toHaveKey('custom_data')
        ->and($resource)->not->toHaveKey('application_token');

    $json = json_encode($resource);
    expect($json)->not->toContain('INTERNAL SECRET NOTES')
        ->and($json)->not->toContain('PAY-SECRET-REF');
});

it('application fee config does not invent a ledger', function () {
    $service = app(StudentApplicationService::class);
    $config = $service->applicationFeeConfig(null);

    expect($config)->toHaveKeys(['required', 'amount', 'fee_type']);
});

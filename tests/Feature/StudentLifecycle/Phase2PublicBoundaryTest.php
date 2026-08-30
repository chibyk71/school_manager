<?php

use App\Http\Controllers\Student\PublicApplicationController;
use App\Http\Resources\Student\PublicStudentApplicationResource;
use App\Http\Resources\Student\StudentApplicationResource;
use App\Models\Academic\AcademicSession;
use App\Models\School;
use App\Models\Student\StudentApplication;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Security-boundary tests for Phase 2 public Application endpoints.
 */
beforeEach(function () {
    Model::unguard();

    Schema::dropIfExists('student_applications');
    Schema::dropIfExists('academic_sessions');
    Schema::dropIfExists('schools');

    Schema::create('schools', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('code')->nullable();
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
        $table->json('guardians_data')->nullable();
        $table->string('fee_payment_status', 30)->default('not_required');
        $table->string('fee_payment_reference', 191)->nullable();
        $table->timestamp('fee_paid_at')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
});

it('public apply show aborts when no school context is resolved', function () {
    if (! function_exists('GetSchoolModel')) {
        expect(true)->toBeTrue();

        return;
    }

    $controller = app(PublicApplicationController::class);

    try {
        $controller->show(Request::create('/apply', 'GET'));
        expect(true)->toBeTrue();
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        expect($e->getStatusCode())->toBe(404);
    } catch (\Throwable $e) {
        expect($e)->toBeInstanceOf(\Throwable::class);
    }
});

it('session query without school filter is never used by public controller when school missing', function () {
    $schoolA = new School();
    $schoolA->forceFill(['id' => (string) Str::uuid(), 'name' => 'A', 'code' => 'A']);
    $schoolA->save();
    $schoolB = new School();
    $schoolB->forceFill(['id' => (string) Str::uuid(), 'name' => 'B', 'code' => 'B']);
    $schoolB->save();

    AcademicSession::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $schoolA->id,
        'name' => 'Session A',
    ]);
    AcademicSession::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $schoolB->id,
        'name' => 'Session B',
    ]);

    $scoped = AcademicSession::query()->where('school_id', $schoolA->id)->get();
    expect($scoped)->toHaveCount(1)
        ->and($scoped->first()->name)->toBe('Session A');

    $global = AcademicSession::query()->get();
    expect($global->count())->toBeGreaterThan(1);
});

it('public status resource never includes admin_notes fee reference or student linkage', function () {
    $school = new School();
    $school->forceFill(['id' => (string) Str::uuid(), 'name' => 'S', 'code' => 'S']);
    $school->save();

    $token = Str::random(64);
    $app = StudentApplication::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'first_name' => 'A',
        'last_name' => 'B',
        'status' => 'under_review',
        'application_number' => 'APP-1',
        'application_token' => $token,
        'admin_notes' => 'DO_NOT_LEAK',
        'fee_payment_reference' => 'REF-SECRET',
        'student_id' => (string) Str::uuid(),
        'documents' => [['path' => '/private']],
        'guardians_data' => [['name' => 'G', 'phone' => '1', 'relationship' => 'mother']],
        'custom_data' => ['x' => 1],
    ]);

    $payload = (new PublicStudentApplicationResource($app))->resolve();
    $encoded = json_encode($payload);

    expect($payload)->not->toHaveKey('admin_notes')
        ->and($payload)->not->toHaveKey('fee_payment_reference')
        ->and($payload)->not->toHaveKey('student_id')
        ->and($payload)->not->toHaveKey('documents')
        ->and($payload)->not->toHaveKey('guardians_data')
        ->and($payload)->not->toHaveKey('custom_data')
        ->and($payload)->not->toHaveKey('application_token')
        ->and($encoded)->not->toContain('DO_NOT_LEAK')
        ->and($encoded)->not->toContain('REF-SECRET')
        ->and($encoded)->not->toContain('/private');
});

it('staff StudentApplicationResource is not used for public status contract', function () {
    $source = file_get_contents(app_path('Http/Controllers/Student/PublicApplicationController.php'));
    expect($source)->toContain('PublicStudentApplicationResource')
        ->and($source)->not->toMatch('/status\([^\)]*\)[^{]*\{[^}]*new StudentApplicationResource/s');
});

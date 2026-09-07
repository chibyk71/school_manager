<?php

uses(Tests\TestCase::class);

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
    Schema::dropIfExists('settings');
    Schema::dropIfExists('dynamic_enums');
    Schema::dropIfExists('schools');
    Schema::dropIfExists('users');

    Schema::create('schools', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('code')->nullable();
        $table->string('slug')->nullable();
        $table->json('data')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('users', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->timestamps();
    });

    Schema::create('academic_sessions', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('school_id');
        $table->string('name');
        $table->timestamps();
        $table->softDeletes();
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

    Schema::create('dynamic_enums', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('label')->nullable();
        $table->string('applies_to');
        $table->json('options')->nullable();
        $table->uuid('school_id')->nullable();
        $table->timestamps();
    });

    Schema::create('settings', function (Blueprint $table) {
        $table->id();
        $table->string('key');
        $table->json('value')->nullable();
        $table->nullableUuidMorphs('model');
        $table->timestamps();
    });
});

function makeSchool(string $name = 'School A'): School
{
    $school = new School();
    $school->forceFill([
        'id' => (string) Str::uuid(),
        'name' => $name,
        'code' => strtoupper(substr($name, 0, 3)),
        'slug' => Str::slug($name).'-'.Str::random(4),
    ])->save();

    return $school->fresh();
}

function makeSession(School $school, string $name = '2026/2027'): AcademicSession
{
    $session = new AcademicSession();
    $session->forceFill([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'name' => $name,
    ])->save();

    return $session->fresh();
}

function makeUser(): User
{
    $user = new User();
    $user->forceFill([
        'id' => (string) Str::uuid(),
        'name' => 'Reviewer',
        'email' => 'reviewer-'.Str::random(6).'@example.test',
    ])->save();

    return $user->fresh();
}

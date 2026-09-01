<?php

uses(Tests\TestCase::class);

use App\Helpers\IdGenerator;
use App\Models\IdSequence;
use App\Models\School;
use App\Models\Student\Student;
use App\Models\Student\StudentSessionPlacement;
use App\Models\User;
use App\Services\Student\PlacementAllocationService;
use App\Services\Student\RegistrationNumberService;
use App\Services\Student\StudentPlacementService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['activitylog.enabled' => false]);
    Model::unguard();
    Schema::dropIfExists('id_sequences');
    Schema::dropIfExists('students');
    Schema::dropIfExists('profiles');
    Schema::dropIfExists('schools');
    Schema::create('schools', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->string('name');
        $t->string('code')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });
    Schema::create('profiles', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->string('first_name')->nullable();
        $t->string('last_name')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });
    Schema::create('students', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id');
        $t->uuid('profile_id');
        $t->string('admission_number')->nullable();
        $t->date('admission_date')->nullable();
        $t->string('status')->default('active');
        $t->timestamps();
        $t->softDeletes();
        $t->unique(['school_id', 'admission_number'], 'uq_students_school_admission_number');
    });
    Schema::create('id_sequences', function (Blueprint $t) {
        $t->id();
        $t->string('type', 64);
        $t->uuid('school_id')->nullable();
        $t->string('scope_key', 191)->default('');
        $t->unsignedInteger('year')->default(0);
        $t->unsignedBigInteger('last_value')->default(0);
        $t->timestamps();
        $t->unique(['type', 'school_id', 'scope_key', 'year'], 'uq_id_sequences_scope');
    });
});

it('generates distinct sequential admission numbers via id_sequences', function () {
    $school = School::query()->create(['id' => (string) Str::uuid(), 'name' => 'S1', 'code' => 'S1']);
    $a = IdGenerator::generate('admission_number', $school);
    $b = IdGenerator::generate('admission_number', $school);
    expect($a)->not->toBe($b)
        ->and((int) IdSequence::query()->where('type', 'admission_number')->where('school_id', $school->id)->value('last_value'))
        ->toBeGreaterThanOrEqual(2);
});

it('rejects mutating an assigned admission number', function () {
    $school = School::query()->create(['id' => (string) Str::uuid(), 'name' => 'S1', 'code' => 'S1']);
    $profileId = (string) Str::uuid();
    \Illuminate\Support\Facades\DB::table('profiles')->insert([
        'id' => $profileId, 'first_name' => 'A', 'last_name' => 'B', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $student = Student::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'profile_id' => $profileId,
        'admission_number' => 'ADM/2026/00001',
        'status' => 'active',
    ]);
    $student->admission_number = 'HACK';
    $student->save();
})->throws(ValidationException::class);

it('enforces school-scoped unique admission numbers at DB level', function () {
    $school = School::query()->create(['id' => (string) Str::uuid(), 'name' => 'S1', 'code' => 'S1']);
    $p1 = (string) Str::uuid();
    $p2 = (string) Str::uuid();
    \Illuminate\Support\Facades\DB::table('profiles')->insert([
        ['id' => $p1, 'first_name' => 'A', 'last_name' => 'B', 'created_at' => now(), 'updated_at' => now()],
        ['id' => $p2, 'first_name' => 'C', 'last_name' => 'D', 'created_at' => now(), 'updated_at' => now()],
    ]);
    Student::query()->create(['id' => (string) Str::uuid(), 'school_id' => $school->id, 'profile_id' => $p1, 'admission_number' => 'ADM/1', 'status' => 'active']);
    Student::query()->create(['id' => (string) Str::uuid(), 'school_id' => $school->id, 'profile_id' => $p2, 'admission_number' => 'ADM/1', 'status' => 'active']);
})->throws(\Illuminate\Database\QueryException::class);

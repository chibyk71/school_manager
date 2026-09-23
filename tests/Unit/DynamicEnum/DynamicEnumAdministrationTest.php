<?php

/**
 * Dynamic Enum Phase 4 — administration service tests.
 */

uses(Tests\TestCase::class);

use App\Models\DynamicEnum;
use App\Models\DynamicEnumOption;
use App\Models\School;
use App\Services\DynamicEnum\DynamicEnumAdministrationService;
use App\Services\DynamicEnum\DynamicEnumLifecycleService;
use App\Services\DynamicEnum\DynamicEnumNotConfiguredException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['activitylog.enabled' => false]);
    Model::unguard();
    phase4BuildPrerequisites();
    phase4RunMigrations();
    $this->lifecycle = app(DynamicEnumLifecycleService::class);
    $this->admin = app(DynamicEnumAdministrationService::class);
});

afterEach(function () {
    phase4DropAll();
});

function phase4EnumsPath(): string
{
    return database_path('migrations/2026_01_02_150221_create_dynamic_enums_table.php');
}

function phase4OptionsPath(): string
{
    return database_path('migrations/2026_01_02_150222_create_dynamic_enum_options_table.php');
}

function phase4SparsePath(): string
{
    return database_path('migrations/2026_09_22_000001_phase2r_sparse_option_ownership.php');
}

function phase4RunMigrations(): void
{
    Schema::dropIfExists('dynamic_enum_options');
    Schema::dropIfExists('dynamic_enums');
    (require phase4EnumsPath())->up();
    (require phase4OptionsPath())->up();
    (require phase4SparsePath())->up();
}

function phase4DropAll(): void
{
    Schema::dropIfExists('dynamic_enum_options');
    Schema::dropIfExists('dynamic_enums');
    Schema::dropIfExists('profiles');
    Schema::dropIfExists('schools');
}

function phase4BuildPrerequisites(): void
{
    phase4DropAll();
    Schema::create('schools', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
    Schema::create('profiles', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('gender')->nullable();
        $table->timestamps();
    });
}

function phase4School(string $name = 'School'): School
{
    $id = (string) Str::uuid();
    DB::table('schools')->insert([
        'id' => $id,
        'name' => $name,
        'created_at' => now(),
        'updated_at' => now(),
        'deleted_at' => null,
    ]);

    return School::query()->withoutGlobalScopes()->findOrFail($id);
}

function phase4SeedGender(): DynamicEnum
{
    $def = app(DynamicEnumLifecycleService::class)->ensureDefinition('profile.gender', 'Gender');
    app(DynamicEnumLifecycleService::class)->createTenantOption($def, 'male', 'Male', ['sort_order' => 1]);
    app(DynamicEnumLifecycleService::class)->createTenantOption($def, 'female', 'Female', ['sort_order' => 2]);

    return $def;
}

test('catalogue lists application definitions only', function () {
    phase4SeedGender();
    $school = phase4School('A');
    DynamicEnum::create([
        'school_id' => $school->id,
        'key' => 'profile.gender',
        'label' => 'School Gender',
    ]);

    $catalogue = $this->admin->catalogue();
    expect($catalogue)->toHaveCount(1)
        ->and($catalogue->first()->school_id)->toBeNull()
        ->and($catalogue->first()->key)->toBe('profile.gender');
});

test('detail exposes effective options with provenance', function () {
    phase4SeedGender();
    $school = phase4School('A');
    $this->admin->createSchoolOverride($school, 'profile.gender', 'male', 'Boy');

    $detail = $this->admin->detail('profile.gender', $school, true, false);
    $male = collect($detail['options'])->firstWhere('value', 'male');
    $female = collect($detail['options'])->firstWhere('value', 'female');

    expect($male['label'])->toBe('Boy')
        ->and($male['overridden'])->toBeTrue()
        ->and($male['source'])->toBe('school')
        ->and($female['label'])->toBe('Female')
        ->and($female['overridden'])->toBeFalse()
        ->and($female['source'])->toBe('tenant');
});

test('tenant option create and requiredness via admin service', function () {
    phase4SeedGender();
    $opt = $this->admin->createTenantOption('profile.gender', 'other', 'Other');
    expect($opt->value)->toBe('other')->and($opt->school_id)->toBeNull();

    $required = $this->admin->makeTenantOptionRequired($opt);
    expect($required->is_required)->toBeTrue();
});

test('school cannot create required option via admin service', function () {
    phase4SeedGender();
    $school = phase4School('A');

    $only = $this->admin->createSchoolOption($school, 'profile.gender', 'student', 'Student', [
        'is_required' => true,
    ]);
    expect($only->is_required)->toBeFalse();
});

test('school override create and reset', function () {
    phase4SeedGender();
    $school = phase4School('A');
    $override = $this->admin->createSchoolOverride($school, 'profile.gender', 'male', 'Boy');
    expect($override->label)->toBe('Boy')->and($override->school_id)->toBe($school->id);

    $this->admin->removeSchoolOverride($school, $override);
    expect(DynamicEnumOption::find($override->id))->toBeNull();

    $resolved = $this->admin->effectiveForSchool($school, 'profile.gender');
    expect($resolved->findByValue('male')->label)->toBe('Male');
});

test('school isolation: school A cannot update school B option', function () {
    phase4SeedGender();
    $schoolA = phase4School('A');
    $schoolB = phase4School('B');
    $optB = $this->admin->createSchoolOverride($schoolB, 'profile.gender', 'male', 'Boy B');

    expect(fn () => $this->admin->updateSchoolOption($schoolA, $optB, ['label' => 'Hacked']))
        ->toThrow(ValidationException::class);
});

test('unknown key fails on detail', function () {
    expect(fn () => $this->admin->detail('missing.key', null, false, true))
        ->toThrow(DynamicEnumNotConfiguredException::class);
});

test('tenant required + school inactive still effective via detail', function () {
    phase4SeedGender();
    $tenant = DynamicEnumOption::whereNull('school_id')->where('value', 'male')->first();
    $this->admin->makeTenantOptionRequired($tenant);
    $school = phase4School('A');
    $override = $this->admin->createSchoolOverride($school, 'profile.gender', 'male', 'Boy');
    $this->admin->deactivateSchoolOption($school, $override);

    $detail = $this->admin->detail('profile.gender', $school, true, true);
    $male = collect($detail['options'])->firstWhere('value', 'male');

    expect($male['is_active'])->toBeTrue()
        ->and($male['enforced'])->toBeTrue()
        ->and($override->fresh()->is_active)->toBeFalse();
});

test('tenant definition presentation update', function () {
    phase4SeedGender();
    $updated = $this->admin->updateTenantDefinitionPresentation('profile.gender', [
        'label' => 'Gender identity',
        'description' => 'Student gender',
    ]);

    expect($updated->label)->toBe('Gender identity')
        ->and($updated->description)->toBe('Student gender')
        ->and($updated->key)->toBe('profile.gender');
});

test('school-only option deletion path', function () {
    phase4SeedGender();
    $school = phase4School('A');
    $only = $this->admin->createSchoolOption($school, 'profile.gender', 'student', 'Student');

    $this->admin->deleteSchoolOption($school, $only);
    expect(DynamicEnumOption::find($only->id))->toBeNull();
});

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

    $required = $this->admin->makeTenantOptionRequired('profile.gender', $opt);
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

    $this->admin->removeSchoolOverride($school, 'profile.gender', $override);
    expect(DynamicEnumOption::find($override->id))->toBeNull();

    $resolved = $this->admin->effectiveForSchool($school, 'profile.gender');
    expect($resolved->findByValue('male')->label)->toBe('Male');
});

test('school isolation: school A cannot update school B option', function () {
    phase4SeedGender();
    $schoolA = phase4School('A');
    $schoolB = phase4School('B');
    $optB = $this->admin->createSchoolOverride($schoolB, 'profile.gender', 'male', 'Boy B');

    expect(fn () => $this->admin->updateSchoolOption($schoolA, 'profile.gender', $optB, ['label' => 'Hacked']))
        ->toThrow(ValidationException::class);
});

test('unknown key fails on detail', function () {
    expect(fn () => $this->admin->detail('missing.key', null, false, true))
        ->toThrow(DynamicEnumNotConfiguredException::class);
});

test('tenant required + school inactive still effective via detail', function () {
    phase4SeedGender();
    $tenant = DynamicEnumOption::whereNull('school_id')->where('value', 'male')->first();
    $this->admin->makeTenantOptionRequired('profile.gender', $tenant);
    $school = phase4School('A');
    $override = $this->admin->createSchoolOverride($school, 'profile.gender', 'male', 'Boy');
    $this->admin->deactivateSchoolOption($school, 'profile.gender', $override);

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

    $this->admin->deleteSchoolOption($school, 'profile.gender', $only);
    expect(DynamicEnumOption::find($only->id))->toBeNull();
});

test('cross-definition option UUID is rejected for tenant mutation', function () {
    phase4SeedGender();
    $status = app(DynamicEnumLifecycleService::class)->ensureDefinition('admission.status', 'Admission status');
    app(DynamicEnumLifecycleService::class)->createTenantOption($status, 'pending', 'Pending');

    $genderOpt = DynamicEnumOption::whereNull('school_id')->where('value', 'male')->first();

    expect(fn () => $this->admin->updateTenantOption('admission.status', $genderOpt, ['label' => 'Hacked']))
        ->toThrow(ValidationException::class);

    expect(fn () => $this->admin->activateTenantOption('admission.status', $genderOpt))
        ->toThrow(ValidationException::class);

    expect(fn () => $this->admin->makeTenantOptionRequired('admission.status', $genderOpt))
        ->toThrow(ValidationException::class);

    expect(fn () => $this->admin->deleteTenantOption('admission.status', $genderOpt))
        ->toThrow(ValidationException::class);

    // Same-definition still works
    $updated = $this->admin->updateTenantOption('profile.gender', $genderOpt, ['label' => 'Male person']);
    expect($updated->label)->toBe('Male person');
});

test('cross-definition option UUID is rejected for school mutation', function () {
    phase4SeedGender();
    $status = app(DynamicEnumLifecycleService::class)->ensureDefinition('admission.status', 'Admission status');
    app(DynamicEnumLifecycleService::class)->createTenantOption($status, 'pending', 'Pending');

    $school = phase4School('A');
    $genderOverride = $this->admin->createSchoolOverride($school, 'profile.gender', 'male', 'Boy');
    $this->admin->createSchoolOverride($school, 'admission.status', 'pending', 'Waiting');

    expect(fn () => $this->admin->updateSchoolOption($school, 'admission.status', $genderOverride, ['label' => 'Hacked']))
        ->toThrow(ValidationException::class);

    expect(fn () => $this->admin->deactivateSchoolOption($school, 'admission.status', $genderOverride))
        ->toThrow(ValidationException::class);

    expect(fn () => $this->admin->removeSchoolOverride($school, 'admission.status', $genderOverride))
        ->toThrow(ValidationException::class);

    expect(fn () => $this->admin->deleteSchoolOption($school, 'admission.status', $genderOverride))
        ->toThrow(ValidationException::class);

    // Same-definition school mutation still works
    $updated = $this->admin->updateSchoolOption($school, 'profile.gender', $genderOverride, ['label' => 'Boy updated']);
    expect($updated->label)->toBe('Boy updated');
});

test('tenant definition presentation succeeds when payload includes tenant HTTP selector', function () {
    phase4SeedGender();

    // Mirrors controller: validated() includes tenant=true; admin service must strip it.
    $updated = $this->admin->updateTenantDefinitionPresentation('profile.gender', [
        'label' => 'Gender identity',
        'description' => 'Student gender',
        'tenant' => true,
    ]);

    expect($updated->label)->toBe('Gender identity')
        ->and($updated->description)->toBe('Student gender')
        ->and($updated->key)->toBe('profile.gender')
        ->and($updated->school_id)->toBeNull();
});

test('school definition presentation partial update inherits tenant label on first create', function () {
    phase4SeedGender();
    $school = phase4School('A');

    // Tenant baseline: label "Gender", description null (from seeder helper).
    $tenant = DynamicEnum::query()->whereNull('school_id')->where('key', 'profile.gender')->first();
    $tenant->update(['label' => 'Gender', 'description' => 'Student gender']);

    // First-time school presentation with only description — must not fall back to machine key.
    $schoolDef = $this->admin->updateSchoolDefinitionPresentation($school, 'profile.gender', [
        'description' => 'Learner gender',
    ]);

    expect($schoolDef->school_id)->toBe($school->id)
        ->and($schoolDef->key)->toBe('profile.gender')
        ->and($schoolDef->label)->toBe('Gender')
        ->and($schoolDef->description)->toBe('Learner gender');

    // Subsequent partial update only touches description; label stays.
    $again = $this->admin->updateSchoolDefinitionPresentation($school, 'profile.gender', [
        'description' => 'Updated learner gender',
    ]);
    expect($again->label)->toBe('Gender')
        ->and($again->description)->toBe('Updated learner gender');
});

test('school definition presentation can override label while keeping tenant description baseline on create', function () {
    phase4SeedGender();
    $school = phase4School('A');
    $tenant = DynamicEnum::query()->whereNull('school_id')->where('key', 'profile.gender')->first();
    $tenant->update(['label' => 'Gender', 'description' => 'Student gender']);

    $schoolDef = $this->admin->updateSchoolDefinitionPresentation($school, 'profile.gender', [
        'label' => 'School gender',
    ]);

    expect($schoolDef->label)->toBe('School gender')
        ->and($schoolDef->description)->toBe('Student gender');
});

test('detail capabilities reflect permission scopes with school context', function () {
    phase4SeedGender();
    $school = phase4School('A');

    $globalsOnly = $this->admin->detail('profile.gender', $school, false, true);
    expect($globalsOnly['capabilities']['can_edit_definition'])->toBeTrue()
        ->and($globalsOnly['capabilities']['can_manage_tenant_options'])->toBeTrue()
        ->and($globalsOnly['capabilities']['can_manage_school_options'])->toBeFalse();

    $schoolOnly = $this->admin->detail('profile.gender', $school, true, false);
    expect($schoolOnly['capabilities']['can_edit_definition'])->toBeTrue()
        ->and($schoolOnly['capabilities']['can_manage_tenant_options'])->toBeFalse()
        ->and($schoolOnly['capabilities']['can_manage_school_options'])->toBeTrue();

    $both = $this->admin->detail('profile.gender', $school, true, true);
    expect($both['capabilities']['can_edit_definition'])->toBeTrue()
        ->and($both['capabilities']['can_manage_tenant_options'])->toBeTrue()
        ->and($both['capabilities']['can_manage_school_options'])->toBeTrue();
});

test('canonical PermissionSeeder includes Phase 4 dynamic enum permissions', function () {
    $source = file_get_contents(database_path('seeders/Settings/PermissionSeeder.php'));

    expect($source)
        ->toContain("'dynamic-enums.view'")
        ->toContain("'dynamic-enums.manage'")
        ->toContain("'dynamic-enums.manageGlobals'");

    // Dedicated seeder remains aligned with the same three names
    $dedicated = file_get_contents(database_path('seeders/Settings/DynamicEnumPermissionSeeder.php'));
    expect($dedicated)
        ->toContain("'dynamic-enums.view'")
        ->toContain("'dynamic-enums.manage'")
        ->toContain("'dynamic-enums.manageGlobals'");

    // Execute the normal seeding path (PermissionSeeder + DynamicEnumPermissionSeeder)
    // and verify the three permissions exist in the database.
    if (! Schema::hasTable('permissions')) {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    app(\Database\Seeders\Settings\PermissionSeeder::class)->run();
    app(\Database\Seeders\Settings\DynamicEnumPermissionSeeder::class)->run();

    $names = \App\Models\Permission::query()
        ->whereIn('name', [
            'dynamic-enums.view',
            'dynamic-enums.manage',
            'dynamic-enums.manageGlobals',
        ])
        ->pluck('name')
        ->sort()
        ->values()
        ->all();

    expect($names)->toBe([
        'dynamic-enums.manage',
        'dynamic-enums.manageGlobals',
        'dynamic-enums.view',
    ]);
});

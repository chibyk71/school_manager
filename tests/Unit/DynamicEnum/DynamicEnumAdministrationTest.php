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

function deAdminMakeSchool(string $name = 'School'): School
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
    $school = deAdminMakeSchool('A');
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
    $school = deAdminMakeSchool('A');
    $this->admin->createSchoolOverride($school, 'profile.gender', 'male', 'Boy');

    $detail = $this->admin->detail('profile.gender', $school, true);
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
    $school = deAdminMakeSchool('A');

    $only = $this->admin->createSchoolOption($school, 'profile.gender', 'student', 'Student', [
        'is_required' => true,
    ]);
    expect($only->is_required)->toBeFalse();
});

test('school override create and reset', function () {
    phase4SeedGender();
    $school = deAdminMakeSchool('A');
    $override = $this->admin->createSchoolOverride($school, 'profile.gender', 'male', 'Boy');
    expect($override->label)->toBe('Boy')->and($override->school_id)->toBe($school->id);

    $this->admin->removeSchoolOverride($school, 'profile.gender', $override);
    expect(DynamicEnumOption::find($override->id))->toBeNull();

    $resolved = $this->admin->effectiveForSchool($school, 'profile.gender');
    expect($resolved->findByValue('male')->label)->toBe('Male');
});

test('school isolation: school A cannot update school B option', function () {
    phase4SeedGender();
    $schoolA = deAdminMakeSchool('A');
    $schoolB = deAdminMakeSchool('B');
    $optB = $this->admin->createSchoolOverride($schoolB, 'profile.gender', 'male', 'Boy B');

    expect(fn () => $this->admin->updateSchoolOption($schoolA, 'profile.gender', $optB, ['label' => 'Hacked']))
        ->toThrow(ValidationException::class);
});

test('unknown key fails on detail', function () {
    expect(fn () => $this->admin->detail('missing.key', null, true))
        ->toThrow(DynamicEnumNotConfiguredException::class);
});

test('tenant required + school inactive still effective via detail', function () {
    phase4SeedGender();
    $tenant = DynamicEnumOption::whereNull('school_id')->where('value', 'male')->first();
    $this->admin->makeTenantOptionRequired('profile.gender', $tenant);
    $school = deAdminMakeSchool('A');
    $override = $this->admin->createSchoolOverride($school, 'profile.gender', 'male', 'Boy');
    $this->admin->deactivateSchoolOption($school, 'profile.gender', $override);

    $detail = $this->admin->detail('profile.gender', $school, true);
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
    $school = deAdminMakeSchool('A');
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

    $school = deAdminMakeSchool('A');
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
    $school = deAdminMakeSchool('A');

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
    $school = deAdminMakeSchool('A');
    $tenant = DynamicEnum::query()->whereNull('school_id')->where('key', 'profile.gender')->first();
    $tenant->update(['label' => 'Gender', 'description' => 'Student gender']);

    $schoolDef = $this->admin->updateSchoolDefinitionPresentation($school, 'profile.gender', [
        'label' => 'School gender',
    ]);

    expect($schoolDef->label)->toBe('School gender')
        ->and($schoolDef->description)->toBe('Student gender');
});

test('detail capabilities follow application context not separate permission names', function () {
    phase4SeedGender();
    $school = deAdminMakeSchool('A');

    // School context + manage → school options only (not tenant).
    $schoolCtx = $this->admin->detail('profile.gender', $school, true);
    expect($schoolCtx['capabilities']['can_edit_definition'])->toBeTrue()
        ->and($schoolCtx['capabilities']['can_manage_tenant_options'])->toBeFalse()
        ->and($schoolCtx['capabilities']['can_manage_school_options'])->toBeTrue()
        ->and($schoolCtx['capabilities']['can_make_required'])->toBeFalse();

    // Tenant context + manage → tenant options only.
    $tenantCtx = $this->admin->detail('profile.gender', null, true);
    expect($tenantCtx['capabilities']['can_edit_definition'])->toBeTrue()
        ->and($tenantCtx['capabilities']['can_manage_tenant_options'])->toBeTrue()
        ->and($tenantCtx['capabilities']['can_manage_school_options'])->toBeFalse()
        ->and($tenantCtx['capabilities']['can_make_required'])->toBeTrue();

    // No manage → no mutations.
    $viewOnly = $this->admin->detail('profile.gender', $school, false);
    expect($viewOnly['capabilities']['can_edit_definition'])->toBeFalse()
        ->and($viewOnly['capabilities']['can_manage_tenant_options'])->toBeFalse()
        ->and($viewOnly['capabilities']['can_manage_school_options'])->toBeFalse();
});


test('school context exposes override and school actions not tenant mutations', function () {
    phase4SeedGender();
    $school = deAdminMakeSchool('A');

    $detail = $this->admin->detail('profile.gender', $school, true);
    $male = collect($detail['options'])->firstWhere('value', 'male');

    expect($male['overridden'])->toBeFalse()
        ->and($male['tenant_option_id'])->not->toBeNull()
        ->and($male['school_option_id'])->toBeNull()
        ->and($male['capabilities']['can_edit_tenant'])->toBeFalse()
        ->and($male['capabilities']['can_edit_school'])->toBeFalse()
        ->and($male['capabilities']['can_edit'])->toBeFalse()
        ->and($male['capabilities']['can_make_required'])->toBeFalse()
        ->and($male['capabilities']['can_override'])->toBeTrue()
        ->and($male['capabilities']['can_reset'])->toBeFalse();

    // Tenant context still allows tenant mutations via the service (controller enforces context).
    $tenantDetail = $this->admin->detail('profile.gender', null, true);
    $tenantMale = collect($tenantDetail['options'])->firstWhere('value', 'male');
    expect($tenantMale['capabilities']['can_edit_tenant'])->toBeTrue()
        ->and($tenantMale['capabilities']['can_make_required'])->toBeTrue();

    $tenantOpt = DynamicEnumOption::findOrFail($tenantMale['tenant_option_id']);
    $updated = $this->admin->updateTenantOption('profile.gender', $tenantOpt, ['label' => 'Male person']);
    expect($updated->label)->toBe('Male person');
});

test('school context override row exposes school actions and reset', function () {
    phase4SeedGender();
    $school = deAdminMakeSchool('A');
    $tenantMale = DynamicEnumOption::whereNull('school_id')->where('value', 'male')->firstOrFail();
    $this->admin->updateTenantOption('profile.gender', $tenantMale, [
        'label' => 'Male',
        'sort_order' => 10,
        'color' => '#111111',
        'icon' => 'tenant-icon',
    ]);
    $override = $this->admin->createSchoolOverride($school, 'profile.gender', 'male', 'Boy', [
        'sort_order' => 99,
        'color' => '#222222',
        'icon' => 'school-icon',
    ]);

    $detail = $this->admin->detail('profile.gender', $school, true);
    $male = collect($detail['options'])->firstWhere('value', 'male');

    expect($male['overridden'])->toBeTrue()
        ->and($male['label'])->toBe('Boy')
        ->and($male['tenant_label'])->toBe('Male')
        ->and($male['school_label'])->toBe('Boy')
        ->and($male['school_option_id'])->toBe($override->id)
        ->and($male['capabilities']['can_edit_tenant'])->toBeFalse()
        ->and($male['capabilities']['can_edit_school'])->toBeTrue()
        ->and($male['capabilities']['can_reset'])->toBeTrue()
        ->and($male['capabilities']['can_make_required'])->toBeFalse();

    $updated = $this->admin->updateSchoolOption($school, 'profile.gender', $override, ['label' => 'Boy updated']);
    expect($updated->label)->toBe('Boy updated');
});

test('school-only option is deletable under school context', function () {
    phase4SeedGender();
    $school = deAdminMakeSchool('A');
    $schoolOnly = $this->admin->createSchoolOption($school, 'profile.gender', 'student', 'Student');

    $detail = $this->admin->detail('profile.gender', $school, true);
    $student = collect($detail['options'])->firstWhere('value', 'student');

    expect($student['capabilities']['can_edit_school'])->toBeTrue()
        ->and($student['capabilities']['can_delete_school'])->toBeTrue()
        ->and($student['capabilities']['can_edit_tenant'])->toBeFalse()
        ->and($student['capabilities']['can_make_required'])->toBeFalse();
});

test('effective school catalogue returns options once with source indicators', function () {
    phase4SeedGender();
    $school = deAdminMakeSchool('A');
    $this->admin->createSchoolOverride($school, 'profile.gender', 'male', 'Boy');
    $this->admin->createSchoolOption($school, 'profile.gender', 'student', 'Student');

    $rows = $this->admin->effectiveSchoolCatalogue($school);
    $gender = collect($rows)->firstWhere('key', 'profile.gender');
    expect($gender)->not->toBeNull();

    $byValue = collect($gender['options'])->keyBy('value');
    expect($byValue->has('male'))->toBeTrue()
        ->and($byValue['male']['source'])->toBe('overridden')
        ->and($byValue['male']['label'])->toBe('Boy')
        ->and($byValue['female']['source'])->toBe('inherited')
        ->and($byValue['student']['source'])->toBe('school-created');

    // No duplicate male rows
    expect(collect($gender['options'])->where('value', 'male')->count())->toBe(1);
});


test('canonical PermissionSeeder includes Phase 7 scope-neutral dynamic enum permissions', function () {
    $source = file_get_contents(database_path('seeders/Settings/PermissionSeeder.php'));

    expect($source)
        ->toContain("'dynamic-enums.view'")
        ->toContain("'dynamic-enums.manage'");
    expect(preg_match("/\['name' => 'dynamic-enums\.manageGlobals'/", $source))->toBe(0);

    $dedicated = file_get_contents(database_path('seeders/Settings/DynamicEnumPermissionSeeder.php'));
    expect($dedicated)
        ->toContain("'dynamic-enums.view'")
        ->toContain("'dynamic-enums.manage'");

    if (! Schema::hasTable('permissions')) {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    \App\Models\Permission::query()->updateOrCreate(
        ['name' => 'dynamic-enums.manageGlobals'],
        ['display_name' => 'obsolete', 'description' => 'obsolete']
    );

    app(\Database\Seeders\Settings\PermissionSeeder::class)->run();
    app(\Database\Seeders\Settings\DynamicEnumPermissionSeeder::class)->run();

    $names = \App\Models\Permission::query()
        ->where('name', 'like', 'dynamic-enums.%')
        ->pluck('name')
        ->sort()
        ->values()
        ->all();

    expect($names)->toBe([
        'dynamic-enums.manage',
        'dynamic-enums.view',
    ]);
});

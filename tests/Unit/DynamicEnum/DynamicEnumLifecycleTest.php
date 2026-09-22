<?php

/**
 * Dynamic Enum Phase 2R — sparse overlay lifecycle tests.
 */

uses(Tests\TestCase::class);

use App\Models\DynamicEnum;
use App\Models\DynamicEnumOption;
use App\Models\School;
use App\Services\DynamicEnum\DynamicEnumLifecycleService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['activitylog.enabled' => false]);
    Model::unguard();
    buildPhase2rPrerequisites();
    runPhase2rMigrations();
    $this->lifecycle = app(DynamicEnumLifecycleService::class);
});

afterEach(function () {
    dropPhase2rAll();
});

function phase2rEnumsPath(): string
{
    return database_path('migrations/2026_01_02_150221_create_dynamic_enums_table.php');
}

function phase2rOptionsPath(): string
{
    return database_path('migrations/2026_01_02_150222_create_dynamic_enum_options_table.php');
}

function phase2rSparsePath(): string
{
    return database_path('migrations/2026_09_22_000001_phase2r_sparse_option_ownership.php');
}

function runPhase2rMigrations(): void
{
    Schema::dropIfExists('dynamic_enum_options');
    Schema::dropIfExists('dynamic_enums');
    (require phase2rEnumsPath())->up();
    (require phase2rOptionsPath())->up();
    (require phase2rSparsePath())->up();
}

function dropPhase2rAll(): void
{
    Schema::dropIfExists('dynamic_enum_options');
    Schema::dropIfExists('dynamic_enums');
    Schema::dropIfExists('profiles');
    Schema::dropIfExists('schools');
}

function buildPhase2rPrerequisites(): void
{
    dropPhase2rAll();
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

function phase2rSchool(string $name = 'School'): School
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

function phase2rDefinition(): DynamicEnum
{
    return app(DynamicEnumLifecycleService::class)->ensureDefinition('profile.gender', 'Gender');
}

test('creates tenant option on application definition', function () {
    $def = phase2rDefinition();
    $opt = $this->lifecycle->createTenantOption($def, 'male', 'Male', ['sort_order' => 1]);
    expect($opt->value)->toBe('male')
        ->and($opt->school_id)->toBeNull()
        ->and($opt->is_active)->toBeTrue()
        ->and($opt->isTenantOption())->toBeTrue();
});

test('updates tenant option presentation without changing value', function () {
    $def = phase2rDefinition();
    $opt = $this->lifecycle->createTenantOption($def, 'male', 'Male');
    $updated = $this->lifecycle->updateOptionPresentation($opt, [
        'label' => 'Man',
        'sort_order' => 2,
        'color' => 'blue',
        'icon' => 'user',
    ]);
    expect($updated->label)->toBe('Man')
        ->and($updated->value)->toBe('male')
        ->and($updated->sort_order)->toBe(2);
});

test('rejects option value mutation via model', function () {
    $def = phase2rDefinition();
    $opt = $this->lifecycle->createTenantOption($def, 'male', 'Male');
    $opt->value = 'man';
    expect(fn () => $opt->save())->toThrow(\RuntimeException::class);
});

test('rejects option school_id mutation via model', function () {
    $def = phase2rDefinition();
    $opt = $this->lifecycle->createTenantOption($def, 'male', 'Male');
    $opt->school_id = (string) Str::uuid();
    expect(fn () => $opt->save())->toThrow(\RuntimeException::class);
});

test('deactivates and restores optional tenant option', function () {
    $def = phase2rDefinition();
    $opt = $this->lifecycle->createTenantOption($def, 'other', 'Other');
    $this->lifecycle->deactivateOption($opt);
    expect($opt->fresh()->is_active)->toBeFalse()->and($opt->fresh()->exists)->toBeTrue();
    $this->lifecycle->restoreOption($opt->fresh());
    expect($opt->fresh()->is_active)->toBeTrue();
});

test('required option cannot be deactivated', function () {
    $def = phase2rDefinition();
    $opt = $this->lifecycle->createTenantOption($def, 'male', 'Male', ['is_required' => true]);
    expect(fn () => $this->lifecycle->deactivateOption($opt))->toThrow(ValidationException::class);
});

test('inactive option cannot become required', function () {
    $def = phase2rDefinition();
    $opt = $this->lifecycle->createTenantOption($def, 'other', 'Other');
    $this->lifecycle->deactivateOption($opt);
    expect(fn () => $this->lifecycle->makeOptionRequired($opt->fresh()))->toThrow(ValidationException::class);
});

test('duplicate tenant value rejected', function () {
    $def = phase2rDefinition();
    $this->lifecycle->createTenantOption($def, 'male', 'Male');
    expect(fn () => $this->lifecycle->createTenantOption($def, 'male', 'Male again'))
        ->toThrow(ValidationException::class);
});

test('school override does not mutate tenant option', function () {
    $def = phase2rDefinition();
    $tenant = $this->lifecycle->createTenantOption($def, 'male', 'Male');
    $school = phase2rSchool('A');
    $override = $this->lifecycle->createSchoolOverride($def, $school, 'male', 'Cis-Male', [
        'color' => 'green',
    ]);

    expect($override->school_id)->toBe($school->id)
        ->and($override->value)->toBe('male')
        ->and($override->label)->toBe('Cis-Male');
    expect($tenant->fresh()->label)->toBe('Male')
        ->and($tenant->fresh()->color)->toBeNull();
});

test('school does not need copied tenant options', function () {
    $def = phase2rDefinition();
    $this->lifecycle->createTenantOption($def, 'male', 'Male');
    $this->lifecycle->createTenantOption($def, 'female', 'Female');
    $this->lifecycle->createTenantOption($def, 'other', 'Other');

    $school = phase2rSchool('A');
    $this->lifecycle->createSchoolOverride($def, $school, 'male', 'Cis-Male');

    expect(DynamicEnumOption::where('school_id', $school->id)->count())->toBe(1);
    expect(DynamicEnumOption::whereNull('school_id')->count())->toBe(3);
});

test('school can add school-only option without tenant row', function () {
    $def = phase2rDefinition();
    $this->lifecycle->createTenantOption($def, 'male', 'Male');
    $school = phase2rSchool('A');
    $only = $this->lifecycle->createSchoolOption($def, $school, 'trans-male', 'Trans-Male');

    expect($only->value)->toBe('trans-male')
        ->and($only->school_id)->toBe($school->id);
    expect(DynamicEnumOption::whereNull('school_id')->where('value', 'trans-male')->exists())->toBeFalse();
});

test('same value can exist independently for tenant and multiple schools', function () {
    $def = phase2rDefinition();
    $this->lifecycle->createTenantOption($def, 'male', 'Male');
    $schoolA = phase2rSchool('A');
    $schoolB = phase2rSchool('B');
    $this->lifecycle->createSchoolOverride($def, $schoolA, 'male', 'Cis-Male A');
    $this->lifecycle->createSchoolOverride($def, $schoolB, 'male', 'Cis-Male B');

    expect(DynamicEnumOption::where('value', 'male')->count())->toBe(3);
});

test('school can deactivate its own override without touching tenant', function () {
    $def = phase2rDefinition();
    $tenant = $this->lifecycle->createTenantOption($def, 'other', 'Other', ['is_required' => true]);
    $school = phase2rSchool('A');
    $override = $this->lifecycle->createSchoolOverride($def, $school, 'other', 'Other', [
        'is_active' => true,
    ]);
    $this->lifecycle->deactivateOption($override);
    expect($override->fresh()->is_active)->toBeFalse();
    expect($tenant->fresh()->is_active)->toBeTrue()->and($tenant->fresh()->is_required)->toBeTrue();
});

test('removing school override leaves tenant untouched', function () {
    $def = phase2rDefinition();
    $tenant = $this->lifecycle->createTenantOption($def, 'male', 'Male');
    $school = phase2rSchool('A');
    $override = $this->lifecycle->createSchoolOverride($def, $school, 'male', 'Cis-Male');
    $this->lifecycle->removeSchoolOverride($override);

    expect(DynamicEnumOption::find($override->id))->toBeNull();
    expect($tenant->fresh()->label)->toBe('Male');
});

test('tenant presentation change does not overwrite school override', function () {
    $def = phase2rDefinition();
    $tenant = $this->lifecycle->createTenantOption($def, 'male', 'Male');
    $school = phase2rSchool('A');
    $override = $this->lifecycle->createSchoolOverride($def, $school, 'male', 'Cis-Male');
    $this->lifecycle->updateOptionPresentation($tenant, ['label' => 'Man']);

    expect($tenant->fresh()->label)->toBe('Man');
    expect($override->fresh()->label)->toBe('Cis-Male');
});

test('tenant addition does not create school rows', function () {
    $def = phase2rDefinition();
    $school = phase2rSchool('A');
    $this->lifecycle->createTenantOption($def, 'male', 'Male');
    $this->lifecycle->createTenantOption($def, 'new-value', 'New');

    expect(DynamicEnumOption::where('school_id', $school->id)->count())->toBe(0);
});

test('tenant deactivation does not mutate school override', function () {
    $def = phase2rDefinition();
    $tenant = $this->lifecycle->createTenantOption($def, 'other', 'Other');
    $school = phase2rSchool('A');
    $override = $this->lifecycle->createSchoolOverride($def, $school, 'other', 'Other School');
    $this->lifecycle->deactivateOption($tenant);

    expect($tenant->fresh()->is_active)->toBeFalse();
    expect($override->fresh()->is_active)->toBeTrue();
});

test('school A cannot modify school B option via ownership guard', function () {
    $def = phase2rDefinition();
    $this->lifecycle->createTenantOption($def, 'male', 'Male');
    $schoolA = phase2rSchool('A');
    $schoolB = phase2rSchool('B');
    $optB = $this->lifecycle->createSchoolOverride($def, $schoolB, 'male', 'Male B');

    expect(fn () => $this->lifecycle->assertSchoolOwnsOption($optB, $schoolA))
        ->toThrow(ValidationException::class);
});

test('school operations cannot treat tenant option as school-owned', function () {
    $def = phase2rDefinition();
    $tenant = $this->lifecycle->createTenantOption($def, 'male', 'Male');
    expect(fn () => $this->lifecycle->removeSchoolOverride($tenant))
        ->toThrow(ValidationException::class);
});

test('permanent tenant deletion succeeds when unreferenced and no school overlays', function () {
    $def = phase2rDefinition();
    $opt = $this->lifecycle->createTenantOption($def, 'prefer_not', 'Prefer not');
    $this->lifecycle->permanentlyDeleteTenantOption($opt);
    expect(DynamicEnumOption::find($opt->id))->toBeNull();
});

test('permanent tenant deletion blocked when school overlay exists', function () {
    $def = phase2rDefinition();
    $opt = $this->lifecycle->createTenantOption($def, 'male', 'Male');
    $school = phase2rSchool('A');
    $this->lifecycle->createSchoolOverride($def, $school, 'male', 'Cis-Male');

    expect(fn () => $this->lifecycle->permanentlyDeleteTenantOption($opt))
        ->toThrow(ValidationException::class);
    expect(DynamicEnumOption::find($opt->id))->not->toBeNull();
});

test('permanent tenant deletion blocked when business data references value', function () {
    $def = phase2rDefinition();
    $opt = $this->lifecycle->createTenantOption($def, 'male', 'Male');
    DB::table('profiles')->insert([
        'id' => (string) Str::uuid(),
        'gender' => 'male',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => $this->lifecycle->permanentlyDeleteTenantOption($opt))
        ->toThrow(ValidationException::class);
});

test('school override removal allowed when tenant fallback exists even if business data references value', function () {
    $def = phase2rDefinition();
    $this->lifecycle->createTenantOption($def, 'male', 'Male');
    $school = phase2rSchool('A');
    $override = $this->lifecycle->createSchoolOverride($def, $school, 'male', 'Cis-Male');
    DB::table('profiles')->insert([
        'id' => (string) Str::uuid(),
        'gender' => 'male',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->lifecycle->removeSchoolOverride($override);
    expect(DynamicEnumOption::find($override->id))->toBeNull();
    expect(DynamicEnumOption::whereNull('school_id')->where('value', 'male')->exists())->toBeTrue();
});

test('school-only referenced value cannot be permanently deleted', function () {
    $def = phase2rDefinition();
    $school = phase2rSchool('A');
    $only = $this->lifecycle->createSchoolOption($def, $school, 'trans-male', 'Trans-Male');
    DB::table('profiles')->insert([
        'id' => (string) Str::uuid(),
        'gender' => 'trans-male',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => $this->lifecycle->permanentlyDeleteSchoolOption($only))
        ->toThrow(ValidationException::class);
});

test('school-only unreferenced value can be permanently deleted', function () {
    $def = phase2rDefinition();
    $school = phase2rSchool('A');
    $only = $this->lifecycle->createSchoolOption($def, $school, 'trans-male', 'Trans-Male');
    $this->lifecycle->permanentlyDeleteSchoolOption($only);
    expect(DynamicEnumOption::find($only->id))->toBeNull();
});

test('definition key is immutable', function () {
    $def = phase2rDefinition();
    expect(fn () => $this->lifecycle->updateDefinitionPresentation($def, ['key' => 'other']))
        ->toThrow(ValidationException::class);
});

test('createSchoolOption rejects value that already exists on tenant baseline', function () {
    $def = phase2rDefinition();
    $this->lifecycle->createTenantOption($def, 'male', 'Male');
    $school = phase2rSchool('A');

    expect(fn () => $this->lifecycle->createSchoolOption($def, $school, 'male', 'Cis-Male'))
        ->toThrow(ValidationException::class);

    expect(DynamicEnumOption::where('school_id', $school->id)->count())->toBe(0);
});

test('createSchoolOverride succeeds for existing tenant value', function () {
    $def = phase2rDefinition();
    $this->lifecycle->createTenantOption($def, 'male', 'Male');
    $school = phase2rSchool('A');
    $override = $this->lifecycle->createSchoolOverride($def, $school, 'male', 'Cis-Male');

    expect($override->value)->toBe('male')
        ->and($override->school_id)->toBe($school->id)
        ->and($override->label)->toBe('Cis-Male');
});

test('permanent deletion blocked for unregistered Dynamic Enum key', function () {
    $def = $this->lifecycle->ensureDefinition('expense.type', 'Expense Type');
    $opt = $this->lifecycle->createTenantOption($def, 'travel', 'Travel');

    expect(fn () => $this->lifecycle->permanentlyDeleteTenantOption($opt))
        ->toThrow(ValidationException::class);
    expect(DynamicEnumOption::find($opt->id))->not->toBeNull();
});

<?php

/**
 * Dynamic Enum Phase 3R — effective resolution & validation tests.
 */

uses(Tests\TestCase::class);

use App\Models\DynamicEnum;
use App\Models\DynamicEnumOption;
use App\Models\School;
use App\Services\DynamicEnum\DynamicEnumLifecycleService;
use App\Services\DynamicEnum\DynamicEnumNotConfiguredException;
use App\Services\DynamicEnum\DynamicEnumResolver;
use App\Services\DynamicEnum\DynamicEnumValidationStatus;
use App\Services\DynamicEnum\DynamicEnumValidator;
use App\Services\DynamicEnum\DynamicEnumValue;
use App\Services\DynamicEnum\ResolvedDynamicEnumOption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['activitylog.enabled' => false]);
    Model::unguard();
    phase3rBuildPrerequisites();
    phase3rRunMigrations();
    $this->lifecycle = app(DynamicEnumLifecycleService::class);
    $this->resolver = app(DynamicEnumResolver::class);
    $this->validator = app(DynamicEnumValidator::class);
});

afterEach(function () {
    phase3rDropAll();
});

function phase3rEnumsPath(): string
{
    return database_path('migrations/2026_01_02_150221_create_dynamic_enums_table.php');
}

function phase3rOptionsPath(): string
{
    return database_path('migrations/2026_01_02_150222_create_dynamic_enum_options_table.php');
}

function phase3rSparsePath(): string
{
    return database_path('migrations/2026_09_22_000001_phase2r_sparse_option_ownership.php');
}

function phase3rRunMigrations(): void
{
    Schema::dropIfExists('dynamic_enum_options');
    Schema::dropIfExists('dynamic_enums');
    (require phase3rEnumsPath())->up();
    (require phase3rOptionsPath())->up();
    (require phase3rSparsePath())->up();
}

function phase3rDropAll(): void
{
    Schema::dropIfExists('dynamic_enum_options');
    Schema::dropIfExists('dynamic_enums');
    Schema::dropIfExists('profiles');
    Schema::dropIfExists('schools');
}

function phase3rBuildPrerequisites(): void
{
    phase3rDropAll();
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

function phase3rSchool(string $name = 'School'): School
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

function phase3rDefinition(string $key = 'profile.gender', string $label = 'Gender'): DynamicEnum
{
    return app(DynamicEnumLifecycleService::class)->ensureDefinition($key, $label);
}

test('canonicalize trims and lowercases only', function () {
    expect(DynamicEnumValue::canonicalize(' Male '))->toBe('male')
        ->and(DynamicEnumValue::canonicalize('MALE'))->toBe('male')
        ->and(DynamicEnumValue::canonicalize('mAlE'))->toBe('male')
        ->and(DynamicEnumValue::canonicalize('male'))->toBe('male')
        ->and(DynamicEnumValue::canonicalize('male-x'))->toBe('male-x')
        ->and(DynamicEnumValue::canonicalize('male x'))->toBe('male x')
        ->and(DynamicEnumValue::canonicalize('01'))->toBe('01')
        ->and(DynamicEnumValue::canonicalize('1'))->toBe('1');
});

test('lifecycle stores canonical lowercase values', function () {
    $def = phase3rDefinition();
    $opt = $this->lifecycle->createTenantOption($def, ' Male ', 'Male');
    expect($opt->value)->toBe('male');
});

test('tenant resolution returns tenant options with ordering', function () {
    $def = phase3rDefinition();
    $this->lifecycle->createTenantOption($def, 'female', 'Female', ['sort_order' => 2]);
    $this->lifecycle->createTenantOption($def, 'male', 'Male', ['sort_order' => 1]);
    $this->lifecycle->createTenantOption($def, 'other', 'Other', ['sort_order' => 3, 'is_active' => false]);

    $resolved = $this->resolver->resolve('profile.gender');

    expect($resolved->key)->toBe('profile.gender')
        ->and($resolved->options->pluck('value')->all())->toBe(['male', 'female', 'other'])
        ->and($resolved->options->firstWhere('value', 'other')->isActive)->toBeFalse()
        ->and($resolved->activeOptions()->pluck('value')->all())->toBe(['male', 'female']);
});

test('unknown key fails explicitly', function () {
    expect(fn () => $this->resolver->resolve('unknown.key'))
        ->toThrow(DynamicEnumNotConfiguredException::class);
});

test('tenant options are inherited without school rows', function () {
    $def = phase3rDefinition();
    $this->lifecycle->createTenantOption($def, 'male', 'Male', ['sort_order' => 1, 'color' => 'blue']);
    $this->lifecycle->createTenantOption($def, 'female', 'Female', ['sort_order' => 2]);
    $school = phase3rSchool('A');

    $resolved = $this->resolver->resolveForSchool($school, 'profile.gender');

    expect($resolved->options)->toHaveCount(2);
    $male = $resolved->findByValue('male');
    expect($male->source)->toBe(ResolvedDynamicEnumOption::SOURCE_TENANT)
        ->and($male->overridden)->toBeFalse()
        ->and($male->label)->toBe('Male')
        ->and($male->color)->toBe('blue');
    expect(DynamicEnumOption::where('school_id', $school->id)->count())->toBe(0);
});

test('school override replaces tenant option by canonical value', function () {
    $def = phase3rDefinition();
    $this->lifecycle->createTenantOption($def, 'male', 'Male', ['sort_order' => 1, 'color' => 'blue']);
    $this->lifecycle->createTenantOption($def, 'female', 'Female', ['sort_order' => 2]);
    $school = phase3rSchool('A');
    $this->lifecycle->createSchoolOverride($def, $school, 'male', 'Cis-Male', [
        'sort_order' => 10,
        'color' => 'green',
        'icon' => 'user',
    ]);

    $resolved = $this->resolver->resolveForSchool($school, 'profile.gender');

    expect($resolved->options)->toHaveCount(2);
    $male = $resolved->findByValue('male');
    expect($male->label)->toBe('Cis-Male')
        ->and($male->sortOrder)->toBe(10)
        ->and($male->color)->toBe('green')
        ->and($male->icon)->toBe('user')
        ->and($male->source)->toBe(ResolvedDynamicEnumOption::SOURCE_SCHOOL)
        ->and($male->overridden)->toBeTrue();
    $female = $resolved->findByValue('female');
    expect($female->source)->toBe(ResolvedDynamicEnumOption::SOURCE_TENANT);
});

test('case-different school value merges as same canonical option', function () {
    $def = phase3rDefinition();
    $this->lifecycle->createTenantOption($def, 'male', 'Male');
    $school = phase3rSchool('A');
    $this->lifecycle->createSchoolOverride($def, $school, 'MALE', 'Cis-Male');

    $resolved = $this->resolver->resolveForSchool($school, 'profile.gender');
    expect($resolved->options)->toHaveCount(1);
    expect($resolved->findByValue('Male')->label)->toBe('Cis-Male');
});

test('school-only option is included for owning school only', function () {
    $def = phase3rDefinition();
    $this->lifecycle->createTenantOption($def, 'male', 'Male');
    $schoolA = phase3rSchool('A');
    $schoolB = phase3rSchool('B');
    $this->lifecycle->createSchoolOption($def, $schoolA, 'trans-male', 'Trans-Male');

    $resolvedA = $this->resolver->resolveForSchool($schoolA, 'profile.gender');
    $resolvedB = $this->resolver->resolveForSchool($schoolB, 'profile.gender');

    expect($resolvedA->findByValue('trans-male'))->not->toBeNull()
        ->and($resolvedA->findByValue('trans-male')->source)->toBe(ResolvedDynamicEnumOption::SOURCE_SCHOOL)
        ->and($resolvedA->findByValue('trans-male')->overridden)->toBeFalse();
    expect($resolvedB->findByValue('trans-male'))->toBeNull();
    expect($resolvedB->options)->toHaveCount(1);
});

test('activation precedence matrix', function (string $tenantActive, ?string $schoolActive, bool $expectedActive) {
    $def = phase3rDefinition();
    $this->lifecycle->createTenantOption($def, 'opt', 'Opt', [
        'is_active' => $tenantActive === 'active',
    ]);
    $school = phase3rSchool('A');

    if ($schoolActive !== null) {
        $override = $this->lifecycle->createSchoolOverride($def, $school, 'opt', 'Opt School', [
            'is_active' => true,
        ]);
        if ($schoolActive === 'inactive') {
            $this->lifecycle->deactivateOption($override);
        }
    }

    $resolved = $this->resolver->resolveForSchool($school, 'profile.gender');
    expect($resolved->findByValue('opt')->isActive)->toBe($expectedActive);
})->with([
    'tenant active, no school' => ['active', null, true],
    'tenant inactive, no school' => ['inactive', null, false],
    'tenant active, school active' => ['active', 'active', true],
    'tenant active, school inactive' => ['active', 'inactive', false],
    'tenant inactive, school active' => ['inactive', 'active', true],
    'tenant inactive, school inactive' => ['inactive', 'inactive', false],
]);

test('tenant required + school inactive yields enforced active effective state', function () {
    $def = phase3rDefinition();
    $this->lifecycle->createTenantOption($def, 'male', 'Male', ['is_required' => true]);
    $school = phase3rSchool('A');
    $override = $this->lifecycle->createSchoolOverride($def, $school, 'male', 'Cis-Male', [
        'sort_order' => 5,
        'color' => 'red',
        'icon' => 'shield',
    ]);
    $this->lifecycle->deactivateOption($override);

    expect($override->fresh()->is_active)->toBeFalse();

    $resolved = $this->resolver->resolveForSchool($school, 'profile.gender');
    $male = $resolved->findByValue('male');

    expect($male->isActive)->toBeTrue()
        ->and($male->enforced)->toBeTrue()
        ->and($male->enforcementReason)->toBe(ResolvedDynamicEnumOption::ENFORCEMENT_TENANT_REQUIRED)
        ->and($male->label)->toBe('Cis-Male')
        ->and($male->sortOrder)->toBe(5)
        ->and($male->color)->toBe('red')
        ->and($male->icon)->toBe('shield')
        ->and($male->isRequired)->toBeTrue();

    expect($override->fresh()->is_active)->toBeFalse();
});

test('removing tenant required restores school inactive effective state', function () {
    $def = phase3rDefinition();
    $tenant = $this->lifecycle->createTenantOption($def, 'male', 'Male', ['is_required' => true]);
    $school = phase3rSchool('A');
    $override = $this->lifecycle->createSchoolOverride($def, $school, 'male', 'Cis-Male');
    $this->lifecycle->deactivateOption($override);

    expect($this->resolver->resolveForSchool($school, 'profile.gender')->findByValue('male')->isActive)->toBeTrue();

    $this->lifecycle->makeOptionOptional($tenant);

    $male = $this->resolver->resolveForSchool($school, 'profile.gender')->findByValue('male');
    expect($male->isActive)->toBeFalse()
        ->and($male->enforced)->toBeFalse()
        ->and($male->enforcementReason)->toBeNull();
});

test('manually reactivated school override is active without enforcement', function () {
    $def = phase3rDefinition();
    $this->lifecycle->createTenantOption($def, 'male', 'Male', ['is_required' => true]);
    $school = phase3rSchool('A');
    $override = $this->lifecycle->createSchoolOverride($def, $school, 'male', 'Cis-Male');
    $this->lifecycle->deactivateOption($override);
    $this->lifecycle->activateOption($override->fresh());

    $male = $this->resolver->resolveForSchool($school, 'profile.gender')->findByValue('male');
    expect($male->isActive)->toBeTrue()
        ->and($male->enforced)->toBeFalse()
        ->and($male->enforcementReason)->toBeNull();
});

test('findByValue accepts mixed case and whitespace', function () {
    $def = phase3rDefinition();
    $this->lifecycle->createTenantOption($def, 'male', 'Male');
    $resolved = $this->resolver->resolve('profile.gender');

    expect($resolved->findByValue(' Male ')?->value)->toBe('male')
        ->and($resolved->findByValue('MALE')?->value)->toBe('male')
        ->and($resolved->findByValue('mAlE')?->value)->toBe('male');
});

test('inactive options remain recognizable but not selectable', function () {
    $def = phase3rDefinition();
    $opt = $this->lifecycle->createTenantOption($def, 'legacy', 'Legacy');
    $this->lifecycle->deactivateOption($opt);
    $school = phase3rSchool('A');

    $resolved = $this->resolver->resolveForSchool($school, 'profile.gender');
    expect($resolved->findByValue('legacy'))->not->toBeNull()
        ->and($resolved->findByValue('legacy')->isActive)->toBeFalse();

    $result = $this->validator->validate($school, 'profile.gender', 'legacy');
    expect($result->status)->toBe(DynamicEnumValidationStatus::InactiveOption)
        ->and($result->isValid())->toBeFalse();
});

test('validation accepts valid tenant inherited and school options', function () {
    $def = phase3rDefinition();
    $this->lifecycle->createTenantOption($def, 'male', 'Male');
    $this->lifecycle->createTenantOption($def, 'female', 'Female');
    $school = phase3rSchool('A');
    $this->lifecycle->createSchoolOverride($def, $school, 'male', 'Cis-Male');
    $this->lifecycle->createSchoolOption($def, $school, 'trans-male', 'Trans-Male');

    expect($this->validator->validate($school, 'profile.gender', 'male')->isValid())->toBeTrue();
    expect($this->validator->validate($school, 'profile.gender', 'female')->isValid())->toBeTrue();
    expect($this->validator->validate($school, 'profile.gender', 'trans-male')->isValid())->toBeTrue();
    expect($this->validator->validate($school, 'profile.gender', ' MALE ')->isValid())->toBeTrue();
});

test('validation rejects missing inactive and cross-school values', function () {
    $def = phase3rDefinition();
    $this->lifecycle->createTenantOption($def, 'male', 'Male');
    $schoolA = phase3rSchool('A');
    $schoolB = phase3rSchool('B');
    $this->lifecycle->createSchoolOption($def, $schoolA, 'trans-male', 'Trans-Male');
    $legacy = $this->lifecycle->createTenantOption($def, 'legacy', 'Legacy');
    $this->lifecycle->deactivateOption($legacy);

    expect($this->validator->validate($schoolA, 'profile.gender', 'nope')->status)
        ->toBe(DynamicEnumValidationStatus::InvalidOption);
    expect($this->validator->validate($schoolA, 'profile.gender', 'legacy')->status)
        ->toBe(DynamicEnumValidationStatus::InactiveOption);
    expect($this->validator->validate($schoolB, 'profile.gender', 'trans-male')->status)
        ->toBe(DynamicEnumValidationStatus::InvalidOption);
});

test('validation returns definition_not_configured for unknown key', function () {
    $school = phase3rSchool('A');
    $result = $this->validator->validate($school, 'missing.key', 'x');
    expect($result->isDefinitionNotConfigured())->toBeTrue();
});

test('null selection is valid without requiring an option', function () {
    $def = phase3rDefinition();
    $this->lifecycle->createTenantOption($def, 'male', 'Male');
    $school = phase3rSchool('A');
    $result = $this->validator->validate($school, 'profile.gender', null);
    expect($result->isValid())->toBeTrue()->and($result->option)->toBeNull();
});

test('unsaved school fails explicitly', function () {
    $school = new School;
    expect(fn () => $this->resolver->resolveForSchool($school, 'profile.gender'))
        ->toThrow(\InvalidArgumentException::class);
});

test('school isolation: school A overlays do not affect school B', function () {
    $def = phase3rDefinition();
    $this->lifecycle->createTenantOption($def, 'male', 'Male');
    $schoolA = phase3rSchool('A');
    $schoolB = phase3rSchool('B');
    $this->lifecycle->createSchoolOverride($def, $schoolA, 'male', 'Cis-Male A');

    expect($this->resolver->resolveForSchool($schoolA, 'profile.gender')->findByValue('male')->label)
        ->toBe('Cis-Male A');
    expect($this->resolver->resolveForSchool($schoolB, 'profile.gender')->findByValue('male')->label)
        ->toBe('Male');
});

test('resolution does not mutate persisted configuration', function () {
    $def = phase3rDefinition();
    $this->lifecycle->createTenantOption($def, 'male', 'Male', ['is_required' => true]);
    $school = phase3rSchool('A');
    $override = $this->lifecycle->createSchoolOverride($def, $school, 'male', 'Cis-Male');
    $this->lifecycle->deactivateOption($override);

    $beforeOptions = DynamicEnumOption::count();
    $beforeActive = $override->fresh()->is_active;

    $this->resolver->resolveForSchool($school, 'profile.gender');
    $this->validator->validate($school, 'profile.gender', 'male');

    expect(DynamicEnumOption::count())->toBe($beforeOptions)
        ->and($override->fresh()->is_active)->toBe($beforeActive)
        ->and($override->fresh()->is_active)->toBeFalse();
});

test('school definition presentation overrides label without replacing options', function () {
    $def = phase3rDefinition();
    $this->lifecycle->createTenantOption($def, 'male', 'Male');
    $school = phase3rSchool('A');

    DynamicEnum::create([
        'school_id' => $school->id,
        'key' => 'profile.gender',
        'label' => 'School Gender Label',
        'description' => 'School-specific description',
    ]);

    $resolved = $this->resolver->resolveForSchool($school, 'profile.gender');
    expect($resolved->label)->toBe('School Gender Label')
        ->and($resolved->description)->toBe('School-specific description')
        ->and($resolved->options)->toHaveCount(1)
        ->and($resolved->findByValue('male')->label)->toBe('Male');
});

test('school-only option rejects is_required true', function () {
    $def = phase3rDefinition();
    $school = phase3rSchool('A');

    expect(fn () => $this->lifecycle->createSchoolOption($def, $school, 'trans-male', 'Trans-Male', [
        'is_required' => true,
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);

    expect(DynamicEnumOption::where('school_id', $school->id)->count())->toBe(0);
});

test('school override rejects is_required true', function () {
    $def = phase3rDefinition();
    $this->lifecycle->createTenantOption($def, 'male', 'Male');
    $school = phase3rSchool('A');

    expect(fn () => $this->lifecycle->createSchoolOverride($def, $school, 'male', 'Cis-Male', [
        'is_required' => true,
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);

    expect(DynamicEnumOption::where('school_id', $school->id)->count())->toBe(0);
});

test('school options and overrides remain valid with default is_required false', function () {
    $def = phase3rDefinition();
    $this->lifecycle->createTenantOption($def, 'male', 'Male');
    $school = phase3rSchool('A');

    $override = $this->lifecycle->createSchoolOverride($def, $school, 'male', 'Cis-Male');
    $only = $this->lifecycle->createSchoolOption($def, $school, 'trans-male', 'Trans-Male');

    expect($override->is_required)->toBeFalse()
        ->and($only->is_required)->toBeFalse();
});

test('tenant required options continue to work after school required rejection', function () {
    $def = phase3rDefinition();
    $tenant = $this->lifecycle->createTenantOption($def, 'male', 'Male', ['is_required' => true]);
    expect($tenant->is_required)->toBeTrue()->and($tenant->is_active)->toBeTrue();

    $school = phase3rSchool('A');
    $override = $this->lifecycle->createSchoolOverride($def, $school, 'male', 'Cis-Male');
    $this->lifecycle->deactivateOption($override);

    $male = $this->resolver->resolveForSchool($school, 'profile.gender')->findByValue('male');
    expect($male->isActive)->toBeTrue()
        ->and($male->enforced)->toBeTrue()
        ->and($male->isRequired)->toBeTrue()
        ->and($override->fresh()->is_active)->toBeFalse()
        ->and($override->fresh()->is_required)->toBeFalse();
});

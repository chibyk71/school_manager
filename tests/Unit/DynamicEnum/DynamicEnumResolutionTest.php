<?php

/**
 * Dynamic Enum Phase 3 — resolution & validation tests.
 */

uses(Tests\TestCase::class);

use App\Models\DynamicEnum;
use App\Models\DynamicEnumOption;
use App\Models\School;
use App\Services\DynamicEnum\DynamicEnumLifecycleService;
use App\Services\DynamicEnum\DynamicEnumResolver;
use App\Services\DynamicEnum\DynamicEnumValidationStatus;
use App\Services\DynamicEnum\DynamicEnumValidator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['activitylog.enabled' => false]);
    Model::unguard();
    buildResolutionPrerequisites();
    runResolutionMigrations();
    $this->lifecycle = app(DynamicEnumLifecycleService::class);
    $this->resolver = app(DynamicEnumResolver::class);
    $this->validator = app(DynamicEnumValidator::class);
});

afterEach(function () {
    rollbackResolutionMigrations();
    dropResolutionPrerequisites();
});

function resolutionEnumsMigrationPath(): string
{
    return database_path('migrations/2026_01_02_150221_create_dynamic_enums_table.php');
}

function resolutionOptionsMigrationPath(): string
{
    return database_path('migrations/2026_01_02_150222_create_dynamic_enum_options_table.php');
}

function runResolutionMigrations(): void
{
    Schema::dropIfExists('dynamic_enum_options');
    Schema::dropIfExists('dynamic_enums');
    (require resolutionEnumsMigrationPath())->up();
    (require resolutionOptionsMigrationPath())->up();
}

function rollbackResolutionMigrations(): void
{
    if (Schema::hasTable('dynamic_enum_options')) {
        (require resolutionOptionsMigrationPath())->down();
    }
    if (Schema::hasTable('dynamic_enums')) {
        (require resolutionEnumsMigrationPath())->down();
    }
}

function dropResolutionPrerequisites(): void
{
    Schema::dropIfExists('dynamic_enum_options');
    Schema::dropIfExists('dynamic_enums');
    Schema::dropIfExists('schools');
}

function buildResolutionPrerequisites(): void
{
    dropResolutionPrerequisites();
    Schema::create('schools', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
}

function resolutionSchool(string $name = 'School'): School
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

/* ------------------------------------------------------------------ */
/* Resolution                                                         */
/* ------------------------------------------------------------------ */

test('school definition resolves when present', function () {
    $school = resolutionSchool('A');
    $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    $schoolDef = $this->lifecycle->createSchoolDefinition($school, 'expense.type', 'School Expense Type');

    $resolved = $this->resolver->resolve($school, 'expense.type');

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($schoolDef->id)
        ->and($resolved->label)->toBe('School Expense Type');
});

test('tenant default resolves when school definition is absent', function () {
    $school = resolutionSchool('A');
    $default = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');

    $resolved = $this->resolver->resolve($school, 'expense.type');

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($default->id)
        ->and($resolved->school_id)->toBeNull();
});

test('school definition completely overrides tenant without merging options', function () {
    $tenant = $this->lifecycle->createDefaultDefinition('address.type', 'Address Type');
    $this->lifecycle->createOption($tenant, 'home', 'Home');
    $this->lifecycle->createOption($tenant, 'office', 'Office');
    $this->lifecycle->createOption($tenant, 'mailing', 'Mailing');

    $school = resolutionSchool('A');
    $custom = $this->lifecycle->createSchoolCustomization($school, 'address.type', 'Address Type', [
        ['value' => 'home', 'label' => 'Home'],
        ['value' => 'boarding', 'label' => 'Boarding'],
    ]);

    $resolved = $this->resolver->resolveWithOptions($school, 'address.type');

    expect($resolved->id)->toBe($custom->id)
        ->and($resolved->options->pluck('value')->all())->toBe(['home', 'boarding'])
        ->and($resolved->options->pluck('value')->all())->not->toContain('office')
        ->and($resolved->options->pluck('value')->all())->not->toContain('mailing');
});

test('school-only definition resolves without tenant default', function () {
    $school = resolutionSchool('A');
    $schoolDef = $this->lifecycle->createSchoolDefinition($school, 'vehicle.type', 'Vehicle Type');
    $this->lifecycle->createOption($schoolDef, 'bus', 'Bus');

    $resolved = $this->resolver->resolveWithOptions($school, 'vehicle.type');

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($schoolDef->id)
        ->and($resolved->options->pluck('value')->all())->toBe(['bus']);
});

test('missing both school and tenant returns null definition not configured', function () {
    $school = resolutionSchool('A');
    $resolved = $this->resolver->resolve($school, 'unknown.key');
    expect($resolved)->toBeNull();
});

test('school A cannot resolve school B definition', function () {
    $schoolA = resolutionSchool('A');
    $schoolB = resolutionSchool('B');
    $defB = $this->lifecycle->createSchoolDefinition($schoolB, 'vehicle.type', 'B Vehicles');
    $this->lifecycle->createOption($defB, 'van', 'Van');

    $resolvedA = $this->resolver->resolve($schoolA, 'vehicle.type');
    expect($resolvedA)->toBeNull();

    $resolvedB = $this->resolver->resolve($schoolB, 'vehicle.type');
    expect($resolvedB->id)->toBe($defB->id);
});

test('resolver does not mutate data', function () {
    $school = resolutionSchool('A');
    $default = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    $updatedAt = $default->updated_at?->toDateTimeString();

    $this->resolver->resolve($school, 'expense.type');
    $this->resolver->resolveWithOptions($school, 'expense.type');

    expect($default->fresh()->updated_at?->toDateTimeString())->toBe($updatedAt);
    expect(DynamicEnum::count())->toBe(1);
});

test('same key across tenant and multiple schools resolves independently', function () {
    $tenant = $this->lifecycle->createDefaultDefinition('expense.type', 'Tenant');
    $this->lifecycle->createOption($tenant, 'salary', 'Salary');

    $schoolA = resolutionSchool('A');
    $schoolB = resolutionSchool('B');
    $customA = $this->lifecycle->createSchoolCustomization($schoolA, 'expense.type', 'A', [
        ['value' => 'salary', 'label' => 'Salary A'],
        ['value' => 'travel', 'label' => 'Travel A'],
    ]);
    $customB = $this->lifecycle->createSchoolCustomization($schoolB, 'expense.type', 'B', [
        ['value' => 'salary', 'label' => 'Salary B'],
        ['value' => 'utilities', 'label' => 'Utilities B'],
    ]);

    expect($this->resolver->resolve($schoolA, 'expense.type')->id)->toBe($customA->id);
    expect($this->resolver->resolve($schoolB, 'expense.type')->id)->toBe($customB->id);

    $schoolC = resolutionSchool('C');
    expect($this->resolver->resolve($schoolC, 'expense.type')->id)->toBe($tenant->id);
});

/* ------------------------------------------------------------------ */
/* Validation                                                         */
/* ------------------------------------------------------------------ */

test('active option is valid', function () {
    $school = resolutionSchool('A');
    $def = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    $this->lifecycle->createOption($def, 'maintenance', 'Maintenance');

    $result = $this->validator->validate($school, 'expense.type', 'maintenance');

    expect($result->isValid())->toBeTrue()
        ->and($result->status)->toBe(DynamicEnumValidationStatus::Valid)
        ->and($result->option->value)->toBe('maintenance');
});

test('missing option is invalid', function () {
    $school = resolutionSchool('A');
    $def = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    $this->lifecycle->createOption($def, 'maintenance', 'Maintenance');

    $result = $this->validator->validate($school, 'expense.type', 'nonexistent');

    expect($result->isInvalidOption())->toBeTrue()
        ->and($result->status)->toBe(DynamicEnumValidationStatus::InvalidOption)
        ->and($result->definition->id)->toBe($def->id)
        ->and($result->option)->toBeNull();
});

test('inactive option is rejected for new selection but remains recognizable', function () {
    $school = resolutionSchool('A');
    $def = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    $opt = $this->lifecycle->createOption($def, 'travel', 'Travel');
    $this->lifecycle->deactivateOption($opt);

    $result = $this->validator->validate($school, 'expense.type', 'travel');

    expect($result->isInactiveOption())->toBeTrue()
        ->and($result->status)->toBe(DynamicEnumValidationStatus::InactiveOption)
        ->and($result->option->value)->toBe('travel')
        ->and($result->option->is_active)->toBeFalse();

    $found = $this->validator->findOption($school, 'expense.type', 'travel');
    expect($found)->not->toBeNull()
        ->and($found->value)->toBe('travel')
        ->and($found->is_active)->toBeFalse();
});

test('missing definition is distinct from invalid option', function () {
    $school = resolutionSchool('A');

    $result = $this->validator->validate($school, 'missing.key', 'anything');

    expect($result->isDefinitionNotConfigured())->toBeTrue()
        ->and($result->status)->toBe(DynamicEnumValidationStatus::DefinitionNotConfigured)
        ->and($result->isInvalidOption())->toBeFalse();
});

test('null is not rejected by dynamic enum requiredness', function () {
    $school = resolutionSchool('A');
    $def = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    $this->lifecycle->createOption($def, 'salary', 'Salary', ['is_required' => true]);

    $result = $this->validator->validate($school, 'expense.type', null);

    expect($result->isValid())->toBeTrue()
        ->and($result->option)->toBeNull()
        ->and($result->definition?->id)->toBe($def->id);
});

test('tenant is_required does not make business values mandatory', function () {
    $school = resolutionSchool('A');
    $def = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    $this->lifecycle->createOption($def, 'salary', 'Salary', ['is_required' => true]);
    $this->lifecycle->createOption($def, 'travel', 'Travel');

    // Selecting a non-required option is still valid
    $result = $this->validator->validate($school, 'expense.type', 'travel');
    expect($result->isValid())->toBeTrue();
});

test('tenant required options are not merged into school overrides', function () {
    $tenant = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    $this->lifecycle->createOption($tenant, 'salary', 'Salary', ['is_required' => true]);
    $this->lifecycle->createOption($tenant, 'travel', 'Travel');

    $school = resolutionSchool('A');
    // School must include salary (required) but can omit travel
    $this->lifecycle->createSchoolCustomization($school, 'expense.type', 'Expense Type', [
        ['value' => 'salary', 'label' => 'Salary'],
        ['value' => 'transport', 'label' => 'Transport'],
    ]);

    $resolved = $this->resolver->resolveWithOptions($school, 'expense.type');
    expect($resolved->options->pluck('value')->all())->toBe(['salary', 'transport'])
        ->and($resolved->options->pluck('value')->all())->not->toContain('travel');

    $travel = $this->validator->validate($school, 'expense.type', 'travel');
    expect($travel->isInvalidOption())->toBeTrue();
});

test('validation does not mutate configuration', function () {
    $school = resolutionSchool('A');
    $def = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    $opt = $this->lifecycle->createOption($def, 'maintenance', 'Maintenance');
    $optUpdated = $opt->updated_at?->toDateTimeString();

    $this->validator->validate($school, 'expense.type', 'maintenance');
    $this->validator->validate($school, 'expense.type', 'missing');
    $this->validator->validate($school, 'expense.type', null);

    expect($opt->fresh()->updated_at?->toDateTimeString())->toBe($optUpdated);
    expect(DynamicEnumOption::count())->toBe(1);
});

test('school specific values are not accepted by another school', function () {
    $schoolA = resolutionSchool('A');
    $schoolB = resolutionSchool('B');

    $defA = $this->lifecycle->createSchoolDefinition($schoolA, 'vehicle.type', 'Vehicles A');
    $this->lifecycle->createOption($defA, 'bus', 'Bus');

    $defB = $this->lifecycle->createSchoolDefinition($schoolB, 'vehicle.type', 'Vehicles B');
    $this->lifecycle->createOption($defB, 'van', 'Van');

    expect($this->validator->validate($schoolA, 'vehicle.type', 'bus')->isValid())->toBeTrue();
    expect($this->validator->validate($schoolA, 'vehicle.type', 'van')->isInvalidOption())->toBeTrue();
    expect($this->validator->validate($schoolB, 'vehicle.type', 'van')->isValid())->toBeTrue();
    expect($this->validator->validate($schoolB, 'vehicle.type', 'bus')->isInvalidOption())->toBeTrue();
});

test('validation uses school override options not tenant options', function () {
    $tenant = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    $this->lifecycle->createOption($tenant, 'maintenance', 'Maintenance');

    $school = resolutionSchool('A');
    $this->lifecycle->createSchoolCustomization($school, 'expense.type', 'Expense Type', [
        ['value' => 'transport', 'label' => 'Transport'],
    ]);

    expect($this->validator->validate($school, 'expense.type', 'maintenance')->isInvalidOption())->toBeTrue();
    expect($this->validator->validate($school, 'expense.type', 'transport')->isValid())->toBeTrue();
});

test('exact value semantics no silent normalization', function () {
    $school = resolutionSchool('A');
    $def = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    $this->lifecycle->createOption($def, 'Maintenance', 'Maintenance');

    expect($this->validator->validate($school, 'expense.type', 'Maintenance')->isValid())->toBeTrue();
    expect($this->validator->validate($school, 'expense.type', 'maintenance')->isInvalidOption())->toBeTrue();
    expect($this->validator->validate($school, 'expense.type', ' Maintenance')->isInvalidOption())->toBeTrue();
});

test('restored inactive option becomes selectable again', function () {
    $school = resolutionSchool('A');
    $def = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    $opt = $this->lifecycle->createOption($def, 'travel', 'Travel');
    $this->lifecycle->deactivateOption($opt);
    expect($this->validator->validate($school, 'expense.type', 'travel')->isInactiveOption())->toBeTrue();

    $this->lifecycle->restoreOption($opt->fresh());
    expect($this->validator->validate($school, 'expense.type', 'travel')->isValid())->toBeTrue();
});

test('tenant changes after school customization do not affect school resolution', function () {
    $tenant = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    $this->lifecycle->createOption($tenant, 'office', 'Office');

    $school = resolutionSchool('A');
    $this->lifecycle->createSchoolCustomization($school, 'expense.type', 'Expense Type', [
        ['value' => 'office', 'label' => 'Office'],
        ['value' => 'lab', 'label' => 'Lab'],
    ]);

    $office = $tenant->options->firstWhere('value', 'office');
    $this->lifecycle->makeOptionRequired($office);
    $this->lifecycle->createOption($tenant, 'parking', 'Parking');

    $resolved = $this->resolver->resolveWithOptions($school, 'expense.type');
    expect($resolved->options->pluck('value')->all())->toBe(['office', 'lab'])
        ->and($resolved->options->pluck('value')->all())->not->toContain('parking');
});

test('isSelectable matches validate isValid for non-null values', function () {
    $school = resolutionSchool('A');
    $def = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    $this->lifecycle->createOption($def, 'maintenance', 'Maintenance');
    $opt = $this->lifecycle->createOption($def, 'travel', 'Travel');
    $this->lifecycle->deactivateOption($opt);

    expect($this->validator->isSelectable($school, 'expense.type', 'maintenance'))->toBeTrue();
    expect($this->validator->isSelectable($school, 'expense.type', 'travel'))->toBeFalse();
    expect($this->validator->isSelectable($school, 'expense.type', 'missing'))->toBeFalse();
});

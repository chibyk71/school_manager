<?php

/**
 * Dynamic Enum Phase 1 — foundational domain & schema tests.
 *
 * Exercises the real create_dynamic_enums / create_dynamic_enum_options migrations
 * (not hand-copied Schema::create). Prerequisites are minimal stubs so the
 * Dynamic Enum schema itself is the system under test.
 *
 * Verifies database-enforced invariants (uniqueness, foreign keys, defaults),
 * relationships, and isolation of default vs school-specific definitions.
 * Does not implement or test runtime resolution (Phase 3).
 */

uses(Tests\TestCase::class);

use App\Models\DynamicEnum;
use App\Models\DynamicEnumOption;
use App\Models\School;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['activitylog.enabled' => false]);
    Model::unguard();
    buildDynamicEnumPhase1Prerequisites();
    runDynamicEnumMigrations();
});

afterEach(function () {
    rollbackDynamicEnumMigrations();
    dropDynamicEnumPhase1Prerequisites();
});

function dynamicEnumsMigrationPath(): string
{
    return database_path('migrations/2026_01_02_150221_create_dynamic_enums_table.php');
}

function dynamicEnumOptionsMigrationPath(): string
{
    return database_path('migrations/2026_01_02_150222_create_dynamic_enum_options_table.php');
}

function loadDynamicEnumsMigration(): object
{
    return require dynamicEnumsMigrationPath();
}

function loadDynamicEnumOptionsMigration(): object
{
    return require dynamicEnumOptionsMigrationPath();
}

function runDynamicEnumMigrations(): void
{
    Schema::dropIfExists('dynamic_enum_options');
    Schema::dropIfExists('dynamic_enums');
    loadDynamicEnumsMigration()->up();
    loadDynamicEnumOptionsMigration()->up();
}

function rollbackDynamicEnumMigrations(): void
{
    if (Schema::hasTable('dynamic_enum_options')) {
        loadDynamicEnumOptionsMigration()->down();
    }
    if (Schema::hasTable('dynamic_enums')) {
        loadDynamicEnumsMigration()->down();
    }
}

function dropDynamicEnumPhase1Prerequisites(): void
{
    Schema::dropIfExists('dynamic_enum_options');
    Schema::dropIfExists('dynamic_enums');
    Schema::dropIfExists('schools');
}

function buildDynamicEnumPhase1Prerequisites(): void
{
    dropDynamicEnumPhase1Prerequisites();

    Schema::create('schools', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
}

function createSchool(array $overrides = []): School
{
    $id = $overrides['id'] ?? (string) Str::uuid();
    $name = $overrides['name'] ?? 'School '.Str::random(6);

    DB::table('schools')->insert([
        'id' => $id,
        'name' => $name,
        'created_at' => now(),
        'updated_at' => now(),
        'deleted_at' => null,
    ]);

    return School::query()->withoutGlobalScopes()->findOrFail($id);
}

function createDefaultEnum(array $overrides = []): DynamicEnum
{
    return DynamicEnum::create(array_merge([
        'school_id' => null,
        'key' => 'expense.type',
        'label' => 'Expense Type',
        'description' => 'Default expense categories',
    ], $overrides));
}

function createSchoolEnum(School $school, array $overrides = []): DynamicEnum
{
    return DynamicEnum::create(array_merge([
        'school_id' => $school->id,
        'key' => 'expense.type',
        'label' => 'Expense Type',
        'description' => null,
    ], $overrides));
}

test('dynamic_enums and dynamic_enum_options tables exist after migration', function () {
    expect(Schema::hasTable('dynamic_enums'))->toBeTrue();
    expect(Schema::hasTable('dynamic_enum_options'))->toBeTrue();
});

test('dynamic_enums has expected columns', function () {
    $columns = Schema::getColumnListing('dynamic_enums');
    expect($columns)->toContain('id', 'school_id', 'key', 'label', 'description', 'created_at', 'updated_at');
});

test('dynamic_enum_options has expected columns', function () {
    $columns = Schema::getColumnListing('dynamic_enum_options');
    expect($columns)->toContain(
        'id', 'dynamic_enum_id', 'value', 'label', 'sort_order',
        'is_active', 'is_required', 'color', 'icon', 'created_at', 'updated_at',
    );
});

test('migration rollback drops both tables', function () {
    rollbackDynamicEnumMigrations();
    expect(Schema::hasTable('dynamic_enum_options'))->toBeFalse();
    expect(Schema::hasTable('dynamic_enums'))->toBeFalse();
});

test('tenant default definition can be created with school_id null', function () {
    $enum = createDefaultEnum(['key' => 'profile.gender', 'label' => 'Gender']);
    expect($enum->exists)->toBeTrue();
    expect($enum->school_id)->toBeNull();
    expect($enum->key)->toBe('profile.gender');
    expect($enum->label)->toBe('Gender');
    expect($enum->id)->not->toBeEmpty();
});

test('school definition can be created and stores its school', function () {
    $school = createSchool();
    $enum = createSchoolEnum($school, ['key' => 'vehicle.type', 'label' => 'Vehicle Type']);
    expect($enum->school_id)->toBe($school->id);
    expect($enum->key)->toBe('vehicle.type');
    expect($enum->school->id)->toBe($school->id);
});

test('stable key persists', function () {
    $enum = createDefaultEnum(['key' => 'admission.source']);
    expect($enum->fresh()->key)->toBe('admission.source');
});

test('description can be null', function () {
    $enum = createDefaultEnum(['description' => null]);
    expect($enum->fresh()->description)->toBeNull();
});

test('duplicate tenant default key is rejected by the database', function () {
    createDefaultEnum(['key' => 'expense.type']);
    expect(fn () => createDefaultEnum(['key' => 'expense.type']))
        ->toThrow(QueryException::class);
});

test('duplicate school key is rejected by the database', function () {
    $school = createSchool();
    createSchoolEnum($school, ['key' => 'expense.type']);
    expect(fn () => createSchoolEnum($school, ['key' => 'expense.type']))
        ->toThrow(QueryException::class);
});

test('same key can exist for different schools', function () {
    $schoolA = createSchool(['name' => 'School A']);
    $schoolB = createSchool(['name' => 'School B']);
    $enumA = createSchoolEnum($schoolA, ['key' => 'expense.type', 'label' => 'A']);
    $enumB = createSchoolEnum($schoolB, ['key' => 'expense.type', 'label' => 'B']);
    expect($enumA->id)->not->toBe($enumB->id);
    expect($enumA->school_id)->toBe($schoolA->id);
    expect($enumB->school_id)->toBe($schoolB->id);
});

test('tenant default and school-specific definitions with the same key can coexist', function () {
    $school = createSchool();
    $default = createDefaultEnum(['key' => 'expense.type']);
    $schoolEnum = createSchoolEnum($school, ['key' => 'expense.type']);
    expect($default->school_id)->toBeNull();
    expect($schoolEnum->school_id)->toBe($school->id);
    expect($default->key)->toBe($schoolEnum->key);
});

test('definition belongs to school when school_id is non-null', function () {
    $school = createSchool();
    $enum = createSchoolEnum($school);
    expect($enum->school)->toBeInstanceOf(School::class);
    expect($enum->school->id)->toBe($school->id);
});

test('school has many dynamic enums relationship', function () {
    $school = createSchool();
    createSchoolEnum($school, ['key' => 'expense.type']);
    createSchoolEnum($school, ['key' => 'document.type']);
    $school->refresh();
    expect($school->dynamicEnums)->toHaveCount(2);
});

test('invalid school_id is rejected by foreign key', function () {
    expect(fn () => DynamicEnum::create([
        'school_id' => (string) Str::uuid(),
        'key' => 'expense.type',
        'label' => 'Expense Type',
    ]))->toThrow(QueryException::class);
});

test('option belongs to a dynamic enum', function () {
    $enum = createDefaultEnum();
    $option = DynamicEnumOption::create([
        'dynamic_enum_id' => $enum->id,
        'value' => 'maintenance',
        'label' => 'Maintenance',
    ]);
    expect($option->dynamicEnum->id)->toBe($enum->id);
});

test('multiple options can belong to one definition', function () {
    $enum = createDefaultEnum();
    DynamicEnumOption::create(['dynamic_enum_id' => $enum->id, 'value' => 'maintenance', 'label' => 'Maintenance', 'sort_order' => 0]);
    DynamicEnumOption::create(['dynamic_enum_id' => $enum->id, 'value' => 'utilities', 'label' => 'Utilities', 'sort_order' => 1]);
    DynamicEnumOption::create(['dynamic_enum_id' => $enum->id, 'value' => 'transport', 'label' => 'Transport', 'sort_order' => 2]);
    expect($enum->options)->toHaveCount(3);
    expect($enum->options->pluck('value')->all())->toBe(['maintenance', 'utilities', 'transport']);
});

test('duplicate dynamic_enum_id and value is rejected by the database', function () {
    $enum = createDefaultEnum();
    DynamicEnumOption::create(['dynamic_enum_id' => $enum->id, 'value' => 'maintenance', 'label' => 'Maintenance']);
    expect(fn () => DynamicEnumOption::create([
        'dynamic_enum_id' => $enum->id,
        'value' => 'maintenance',
        'label' => 'Maintenance Again',
    ]))->toThrow(QueryException::class);
});

test('same value can exist under different definitions', function () {
    $enumA = createDefaultEnum(['key' => 'expense.type']);
    $enumB = createDefaultEnum(['key' => 'vehicle.type', 'label' => 'Vehicle Type']);
    $optA = DynamicEnumOption::create(['dynamic_enum_id' => $enumA->id, 'value' => 'maintenance', 'label' => 'Maintenance']);
    $optB = DynamicEnumOption::create(['dynamic_enum_id' => $enumB->id, 'value' => 'maintenance', 'label' => 'Maintenance']);
    expect($optA->id)->not->toBe($optB->id);
    expect($optA->value)->toBe($optB->value);
});

test('is_active defaults to true', function () {
    $enum = createDefaultEnum();
    $option = DynamicEnumOption::create(['dynamic_enum_id' => $enum->id, 'value' => 'maintenance', 'label' => 'Maintenance']);
    expect($option->fresh()->is_active)->toBeTrue();
});

test('is_required defaults to false', function () {
    $enum = createDefaultEnum();
    $option = DynamicEnumOption::create(['dynamic_enum_id' => $enum->id, 'value' => 'maintenance', 'label' => 'Maintenance']);
    expect($option->fresh()->is_required)->toBeFalse();
});

test('sort_order persists correctly', function () {
    $enum = createDefaultEnum();
    $option = DynamicEnumOption::create(['dynamic_enum_id' => $enum->id, 'value' => 'utilities', 'label' => 'Utilities', 'sort_order' => 42]);
    expect($option->fresh()->sort_order)->toBe(42);
});

test('sort_order defaults to zero when omitted', function () {
    $enum = createDefaultEnum();
    $option = DynamicEnumOption::create(['dynamic_enum_id' => $enum->id, 'value' => 'supplies', 'label' => 'Supplies']);
    expect($option->fresh()->sort_order)->toBe(0);
});

test('label persists', function () {
    $enum = createDefaultEnum();
    $option = DynamicEnumOption::create(['dynamic_enum_id' => $enum->id, 'value' => 'transport', 'label' => 'Transport & Logistics']);
    expect($option->fresh()->label)->toBe('Transport & Logistics');
});

test('color and icon can be null', function () {
    $enum = createDefaultEnum();
    $option = DynamicEnumOption::create([
        'dynamic_enum_id' => $enum->id,
        'value' => 'maintenance',
        'label' => 'Maintenance',
        'color' => null,
        'icon' => null,
    ]);
    $fresh = $option->fresh();
    expect($fresh->color)->toBeNull();
    expect($fresh->icon)->toBeNull();
});

test('presentation metadata persists correctly', function () {
    $enum = createDefaultEnum();
    $option = DynamicEnumOption::create([
        'dynamic_enum_id' => $enum->id,
        'value' => 'utilities',
        'label' => 'Utilities',
        'color' => 'bg-blue-100',
        'icon' => 'bolt',
        'sort_order' => 3,
        'is_active' => false,
        'is_required' => true,
    ]);
    $fresh = $option->fresh();
    expect($fresh->color)->toBe('bg-blue-100');
    expect($fresh->icon)->toBe('bolt');
    expect($fresh->sort_order)->toBe(3);
    expect($fresh->is_active)->toBeFalse();
    expect($fresh->is_required)->toBeTrue();
});

test('invalid dynamic_enum_id is rejected by foreign key', function () {
    expect(fn () => DynamicEnumOption::create([
        'dynamic_enum_id' => (string) Str::uuid(),
        'value' => 'maintenance',
        'label' => 'Maintenance',
    ]))->toThrow(QueryException::class);
});

test('deleting a definition cascades to its options', function () {
    $enum = createDefaultEnum();
    DynamicEnumOption::create(['dynamic_enum_id' => $enum->id, 'value' => 'maintenance', 'label' => 'Maintenance']);
    DynamicEnumOption::create(['dynamic_enum_id' => $enum->id, 'value' => 'utilities', 'label' => 'Utilities']);
    expect(DynamicEnumOption::count())->toBe(2);
    $enum->delete();
    expect(DynamicEnumOption::count())->toBe(0);
});

test('school A, school B, and tenant default expense.type remain distinct', function () {
    $schoolA = createSchool(['name' => 'School A']);
    $schoolB = createSchool(['name' => 'School B']);
    $default = createDefaultEnum(['key' => 'expense.type', 'label' => 'Default Expense Type']);
    $enumA = createSchoolEnum($schoolA, ['key' => 'expense.type', 'label' => 'School A Expense Type']);
    $enumB = createSchoolEnum($schoolB, ['key' => 'expense.type', 'label' => 'School B Expense Type']);
    DynamicEnumOption::create(['dynamic_enum_id' => $default->id, 'value' => 'maintenance', 'label' => 'Maintenance']);
    DynamicEnumOption::create(['dynamic_enum_id' => $enumA->id, 'value' => 'supplies', 'label' => 'Supplies']);
    DynamicEnumOption::create(['dynamic_enum_id' => $enumB->id, 'value' => 'transport', 'label' => 'Transport']);
    expect(DynamicEnum::count())->toBe(3);
    expect(DynamicEnum::whereNull('school_id')->where('key', 'expense.type')->count())->toBe(1);
    expect(DynamicEnum::where('school_id', $schoolA->id)->where('key', 'expense.type')->count())->toBe(1);
    expect(DynamicEnum::where('school_id', $schoolB->id)->where('key', 'expense.type')->count())->toBe(1);
    expect($default->options()->pluck('value')->all())->toBe(['maintenance']);
    expect($enumA->options()->pluck('value')->all())->toBe(['supplies']);
    expect($enumB->options()->pluck('value')->all())->toBe(['transport']);
});

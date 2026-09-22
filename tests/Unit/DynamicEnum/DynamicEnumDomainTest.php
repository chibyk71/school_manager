<?php

/**
 * Dynamic Enum Phase 1/2R — foundational domain & schema tests.
 *
 * Exercises the real create_dynamic_enums / create_dynamic_enum_options migrations
 * plus Phase 2R sparse option ownership migration.
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
    $sparse = database_path('migrations/2026_09_22_000001_phase2r_sparse_option_ownership.php');
    if (file_exists($sparse)) {
        (require $sparse)->up();
    }
}

function rollbackDynamicEnumMigrations(): void
{
    Schema::dropIfExists('dynamic_enum_options');
    Schema::dropIfExists('dynamic_enums');
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
        'id', 'dynamic_enum_id', 'school_id', 'value', 'label', 'sort_order',
        'is_active', 'is_required', 'color', 'icon', 'created_at', 'updated_at',
    );
});

test('tenant default definition can be created with school_id null', function () {
    $enum = createDefaultEnum(['key' => 'profile.gender', 'label' => 'Gender']);
    expect($enum->exists)->toBeTrue();
    expect($enum->school_id)->toBeNull();
    expect($enum->key)->toBe('profile.gender');
});

test('tenant and school option values can coexist for the same value', function () {
    $enum = createDefaultEnum();
    $school = createSchool();
    DynamicEnumOption::create(['dynamic_enum_id' => $enum->id, 'school_id' => null, 'value' => 'male', 'label' => 'Male']);
    DynamicEnumOption::create(['dynamic_enum_id' => $enum->id, 'school_id' => $school->id, 'value' => 'male', 'label' => 'Cis-Male']);
    expect(DynamicEnumOption::where('value', 'male')->count())->toBe(2);
});

test('duplicate tenant option value is rejected', function () {
    $enum = createDefaultEnum();
    DynamicEnumOption::create(['dynamic_enum_id' => $enum->id, 'school_id' => null, 'value' => 'male', 'label' => 'Male']);
    expect(fn () => DynamicEnumOption::create([
        'dynamic_enum_id' => $enum->id,
        'school_id' => null,
        'value' => 'male',
        'label' => 'Male 2',
    ]))->toThrow(QueryException::class);
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

test('deleting a definition cascades to its options', function () {
    $enum = createDefaultEnum();
    DynamicEnumOption::create(['dynamic_enum_id' => $enum->id, 'value' => 'maintenance', 'label' => 'Maintenance']);
    expect(DynamicEnumOption::count())->toBe(1);
    $enum->delete();
    expect(DynamicEnumOption::count())->toBe(0);
});

test('InDynamicEnum rule does not call removed DynamicEnum scopes', function () {
    $rule = new \App\Rules\InDynamicEnum('type', \App\Models\Address::class);
    $failed = false;
    $rule->validate('type', 'residential', function () use (&$failed) {
        $failed = true;
    });
    expect($failed)->toBeFalse();
});

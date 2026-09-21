<?php

/**
 * Dynamic Enum Phase 2 — definition & option lifecycle tests.
 *
 * Covers identity immutability, activation/required rules, school customization,
 * required-tenant-option preservation, ownership isolation, and atomicity.
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
    buildLifecyclePrerequisites();
    runLifecycleMigrations();
    $this->lifecycle = app(DynamicEnumLifecycleService::class);
});

afterEach(function () {
    rollbackLifecycleMigrations();
    dropLifecyclePrerequisites();
});

/* ------------------------------------------------------------------ */
/* Helpers (exercise real Phase 1 migrations)                         */
/* ------------------------------------------------------------------ */

function lifecycleEnumsMigrationPath(): string
{
    return database_path('migrations/2026_01_02_150221_create_dynamic_enums_table.php');
}

function lifecycleOptionsMigrationPath(): string
{
    return database_path('migrations/2026_01_02_150222_create_dynamic_enum_options_table.php');
}

function runLifecycleMigrations(): void
{
    Schema::dropIfExists('dynamic_enum_options');
    Schema::dropIfExists('dynamic_enums');
    (require lifecycleEnumsMigrationPath())->up();
    (require lifecycleOptionsMigrationPath())->up();
}

function rollbackLifecycleMigrations(): void
{
    if (Schema::hasTable('dynamic_enum_options')) {
        (require lifecycleOptionsMigrationPath())->down();
    }
    if (Schema::hasTable('dynamic_enums')) {
        (require lifecycleEnumsMigrationPath())->down();
    }
}

function dropLifecyclePrerequisites(): void
{
    Schema::dropIfExists('dynamic_enum_options');
    Schema::dropIfExists('dynamic_enums');
    Schema::dropIfExists('schools');
}

function buildLifecyclePrerequisites(): void
{
    dropLifecyclePrerequisites();
    Schema::create('schools', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
}

function makeSchool(string $name = 'School'): School
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
/* Definition lifecycle                                               */
/* ------------------------------------------------------------------ */

test('creates tenant default definition', function () {
    $def = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type', 'Categories');
    expect($def->school_id)->toBeNull()
        ->and($def->key)->toBe('expense.type')
        ->and($def->label)->toBe('Expense Type')
        ->and($def->description)->toBe('Categories')
        ->and($def->isDefault())->toBeTrue();
});

test('creates school definition', function () {
    $school = makeSchool('A');
    $def = $this->lifecycle->createSchoolDefinition($school, 'expense.type', 'Expense Type');
    expect($def->school_id)->toBe($school->id)
        ->and($def->key)->toBe('expense.type')
        ->and($def->isSchoolOwned())->toBeTrue();
});

test('updates definition label and description', function () {
    $def = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    $updated = $this->lifecycle->updateDefinitionPresentation($def, [
        'label' => 'Expense Category',
        'description' => 'Updated',
    ]);
    expect($updated->label)->toBe('Expense Category')
        ->and($updated->description)->toBe('Updated')
        ->and($updated->key)->toBe('expense.type');
});

test('rejects definition key mutation via service', function () {
    $def = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    expect(fn () => $this->lifecycle->updateDefinitionPresentation($def, ['key' => 'other.key']))
        ->toThrow(ValidationException::class);
    expect($def->fresh()->key)->toBe('expense.type');
});

test('rejects definition ownership mutation via service', function () {
    $def = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    expect(fn () => $this->lifecycle->updateDefinitionPresentation($def, ['school_id' => (string) Str::uuid()]))
        ->toThrow(ValidationException::class);
    expect($def->fresh()->school_id)->toBeNull();
});

test('rejects definition key mutation via model', function () {
    $def = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    $def->key = 'other.key';
    expect(fn () => $def->save())->toThrow(\RuntimeException::class);
});

test('rejects duplicate default definition key', function () {
    $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    expect(fn () => $this->lifecycle->createDefaultDefinition('expense.type', 'Again'))
        ->toThrow(ValidationException::class);
});

/* ------------------------------------------------------------------ */
/* Option identity                                                    */
/* ------------------------------------------------------------------ */

test('creates option on definition', function () {
    $def = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    $opt = $this->lifecycle->createOption($def, 'maintenance', 'Maintenance', ['sort_order' => 1]);
    expect($opt->value)->toBe('maintenance')
        ->and($opt->label)->toBe('Maintenance')
        ->and($opt->is_active)->toBeTrue()
        ->and($opt->is_required)->toBeFalse()
        ->and($opt->sort_order)->toBe(1);
});

test('rejects option value mutation via service', function () {
    $def = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    $opt = $this->lifecycle->createOption($def, 'maintenance', 'Maintenance');
    expect(fn () => $this->lifecycle->updateOptionPresentation($opt, ['value' => 'repairs']))
        ->toThrow(ValidationException::class);
    expect($opt->fresh()->value)->toBe('maintenance');
});

test('rejects option value mutation via model', function () {
    $def = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    $opt = $this->lifecycle->createOption($def, 'maintenance', 'Maintenance');
    $opt->value = 'repairs';
    expect(fn () => $opt->save())->toThrow(\RuntimeException::class);
});

test('rejects dynamic_enum_id mutation via model', function () {
    $def = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    $other = $this->lifecycle->createDefaultDefinition('vehicle.type', 'Vehicle Type');
    $opt = $this->lifecycle->createOption($def, 'maintenance', 'Maintenance');
    $opt->dynamic_enum_id = $other->id;
    expect(fn () => $opt->save())->toThrow(\RuntimeException::class);
});

test('updates option presentation fields', function () {
    $def = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    $opt = $this->lifecycle->createOption($def, 'maintenance', 'Maintenance');
    $updated = $this->lifecycle->updateOptionPresentation($opt, [
        'label' => 'Building Maintenance',
        'sort_order' => 5,
        'color' => 'bg-blue-100',
        'icon' => 'wrench',
    ]);
    expect($updated->label)->toBe('Building Maintenance')
        ->and($updated->sort_order)->toBe(5)
        ->and($updated->color)->toBe('bg-blue-100')
        ->and($updated->icon)->toBe('wrench')
        ->and($updated->value)->toBe('maintenance');
});

test('rejects duplicate option value on same definition', function () {
    $def = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    $this->lifecycle->createOption($def, 'maintenance', 'Maintenance');
    expect(fn () => $this->lifecycle->createOption($def, 'maintenance', 'Again'))
        ->toThrow(ValidationException::class);
});

/* ------------------------------------------------------------------ */
/* Activation / required                                              */
/* ------------------------------------------------------------------ */

test('deactivates optional active option', function () {
    $def = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    $opt = $this->lifecycle->createOption($def, 'travel', 'Travel');
    $inactive = $this->lifecycle->deactivateOption($opt);
    expect($inactive->is_active)->toBeFalse()
        ->and($inactive->fresh()->exists)->toBeTrue();
});

test('restores inactive option', function () {
    $def = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    $opt = $this->lifecycle->createOption($def, 'travel', 'Travel');
    $this->lifecycle->deactivateOption($opt);
    $restored = $this->lifecycle->restoreOption($opt->fresh());
    expect($restored->is_active)->toBeTrue();
});

test('rejects deactivation of required option', function () {
    $def = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    $opt = $this->lifecycle->createOption($def, 'salary', 'Salary', ['is_required' => true]);
    expect(fn () => $this->lifecycle->deactivateOption($opt))
        ->toThrow(ValidationException::class);
    expect($opt->fresh()->is_active)->toBeTrue();
});

test('rejects making inactive option required', function () {
    $def = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    $opt = $this->lifecycle->createOption($def, 'travel', 'Travel');
    $this->lifecycle->deactivateOption($opt);
    expect(fn () => $this->lifecycle->makeOptionRequired($opt->fresh()))
        ->toThrow(ValidationException::class);
    expect($opt->fresh()->is_required)->toBeFalse();
});

test('allows active optional to become required', function () {
    $def = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    $opt = $this->lifecycle->createOption($def, 'salary', 'Salary');
    $required = $this->lifecycle->makeOptionRequired($opt);
    expect($required->is_required)->toBeTrue()
        ->and($required->is_active)->toBeTrue();
});

test('allows required option to become optional then inactive', function () {
    $def = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    $opt = $this->lifecycle->createOption($def, 'salary', 'Salary', ['is_required' => true]);
    $optional = $this->lifecycle->makeOptionOptional($opt);
    expect($optional->is_required)->toBeFalse();
    $inactive = $this->lifecycle->deactivateOption($optional);
    expect($inactive->is_active)->toBeFalse()
        ->and($inactive->exists)->toBeTrue();
});

test('rejects creating required inactive option', function () {
    $def = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    expect(fn () => $this->lifecycle->createOption($def, 'salary', 'Salary', [
        'is_required' => true,
        'is_active' => false,
    ]))->toThrow(ValidationException::class);
});

test('model rejects required plus inactive state', function () {
    $def = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    $opt = $this->lifecycle->createOption($def, 'salary', 'Salary', ['is_required' => true]);
    $opt->is_active = false;
    expect(fn () => $opt->save())->toThrow(\RuntimeException::class);
});

/* ------------------------------------------------------------------ */
/* School customization                                               */
/* ------------------------------------------------------------------ */

test('creates school customization as complete replacement', function () {
    $this->lifecycle->createDefaultDefinition('address.type', 'Address Type');
    $this->lifecycle->createOption(
        DynamicEnum::whereNull('school_id')->where('key', 'address.type')->first(),
        'home',
        'Home'
    );
    $this->lifecycle->createOption(
        DynamicEnum::whereNull('school_id')->where('key', 'address.type')->first(),
        'office',
        'Office'
    );

    $school = makeSchool('A');
    $custom = $this->lifecycle->createSchoolCustomization($school, 'address.type', 'Address Type', [
        ['value' => 'home', 'label' => 'Home'],
        ['value' => 'office', 'label' => 'Office'],
        ['value' => 'boarding', 'label' => 'Boarding'],
    ]);

    expect($custom->school_id)->toBe($school->id)
        ->and($custom->options)->toHaveCount(3)
        ->and($custom->options->pluck('value')->all())->toBe(['home', 'office', 'boarding']);

    // Tenant remains unchanged
    $tenant = DynamicEnum::whereNull('school_id')->where('key', 'address.type')->with('options')->first();
    expect($tenant->options)->toHaveCount(2)
        ->and($tenant->options->pluck('value')->all())->toBe(['home', 'office']);
});

test('school customization must preserve required tenant option', function () {
    $tenant = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    $this->lifecycle->createOption($tenant, 'salary', 'Salary', ['is_required' => true]);
    $this->lifecycle->createOption($tenant, 'travel', 'Travel');

    $school = makeSchool('A');
    expect(fn () => $this->lifecycle->createSchoolCustomization($school, 'expense.type', 'Expense Type', [
        ['value' => 'travel', 'label' => 'Travel'],
    ]))->toThrow(ValidationException::class);

    expect(DynamicEnum::where('school_id', $school->id)->count())->toBe(0);
});

test('school customization cannot deactivate required tenant option', function () {
    $tenant = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    $this->lifecycle->createOption($tenant, 'salary', 'Salary', ['is_required' => true]);

    $school = makeSchool('A');
    expect(fn () => $this->lifecycle->createSchoolCustomization($school, 'expense.type', 'Expense Type', [
        ['value' => 'salary', 'label' => 'Salary', 'is_active' => false],
    ]))->toThrow(ValidationException::class);

    expect(DynamicEnum::where('school_id', $school->id)->count())->toBe(0);
});

test('school may customize presentation of required tenant option', function () {
    $tenant = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    $this->lifecycle->createOption($tenant, 'office', 'Office', [
        'is_required' => true,
        'color' => 'blue',
        'icon' => 'building',
    ]);

    $school = makeSchool('A');
    $custom = $this->lifecycle->createSchoolCustomization($school, 'expense.type', 'Expense Type', [
        [
            'value' => 'office',
            'label' => 'Administration Office',
            'color' => 'green',
            'icon' => 'briefcase',
            'sort_order' => 0,
        ],
    ]);

    $schoolOption = $custom->options->firstWhere('value', 'office');
    expect($schoolOption->label)->toBe('Administration Office')
        ->and($schoolOption->color)->toBe('green')
        ->and($schoolOption->icon)->toBe('briefcase');

    $tenantOption = $tenant->fresh()->options->firstWhere('value', 'office');
    expect($tenantOption->label)->toBe('Office')
        ->and($tenantOption->color)->toBe('blue')
        ->and($tenantOption->icon)->toBe('building');
});

test('school A cannot modify school B definition via ownership guard', function () {
    $schoolA = makeSchool('A');
    $schoolB = makeSchool('B');
    $defB = $this->lifecycle->createSchoolDefinition($schoolB, 'expense.type', 'Expense Type');

    expect(fn () => $this->lifecycle->assertOwnedBySchool($defB, $schoolA))
        ->toThrow(ValidationException::class);
});

test('school operations reject tenant definitions', function () {
    $tenant = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    expect(fn () => $this->lifecycle->assertIsSchoolDefinition($tenant))
        ->toThrow(ValidationException::class);
    expect(fn () => $this->lifecycle->revertSchoolCustomization($tenant))
        ->toThrow(ValidationException::class);
});

test('revert removes school customization without touching tenant', function () {
    $tenant = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    $this->lifecycle->createOption($tenant, 'salary', 'Salary', ['is_required' => true]);
    $this->lifecycle->createOption($tenant, 'travel', 'Travel');

    $school = makeSchool('A');
    $custom = $this->lifecycle->createSchoolCustomization($school, 'expense.type', 'Expense Type', [
        ['value' => 'salary', 'label' => 'Salary'],
        ['value' => 'travel', 'label' => 'Travel'],
        ['value' => 'supplies', 'label' => 'Supplies'],
    ]);

    $customId = $custom->id;
    expect(DynamicEnumOption::where('dynamic_enum_id', $customId)->count())->toBe(3);

    $this->lifecycle->revertSchoolCustomization($custom);

    expect(DynamicEnum::find($customId))->toBeNull();
    expect(DynamicEnumOption::where('dynamic_enum_id', $customId)->count())->toBe(0);
    expect(DynamicEnum::whereNull('school_id')->where('key', 'expense.type')->exists())->toBeTrue();
    expect($tenant->fresh()->options)->toHaveCount(2);
});

test('invalid customization leaves no partial definition', function () {
    $tenant = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    $this->lifecycle->createOption($tenant, 'salary', 'Salary', ['is_required' => true]);

    $school = makeSchool('A');
    try {
        $this->lifecycle->createSchoolCustomization($school, 'expense.type', 'Expense Type', [
            ['value' => 'travel', 'label' => 'Travel'], // missing salary
        ]);
        expect(false)->toBeTrue(); // should not reach
    } catch (ValidationException) {
        // expected
    }

    expect(DynamicEnum::where('school_id', $school->id)->count())->toBe(0);
    expect(DynamicEnumOption::count())->toBe(1); // only tenant salary
});

test('update school customization replaces options atomically', function () {
    $tenant = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    $this->lifecycle->createOption($tenant, 'salary', 'Salary', ['is_required' => true]);

    $school = makeSchool('A');
    $custom = $this->lifecycle->createSchoolCustomization($school, 'expense.type', 'Expense Type', [
        ['value' => 'salary', 'label' => 'Salary'],
        ['value' => 'travel', 'label' => 'Travel'],
    ]);

    $updated = $this->lifecycle->updateSchoolCustomization($custom, ['label' => 'Expenses'], [
        ['value' => 'salary', 'label' => 'Staff Salary'],
        ['value' => 'utilities', 'label' => 'Utilities'],
    ]);

    expect($updated->label)->toBe('Expenses')
        ->and($updated->options->pluck('value')->sort()->values()->all())->toBe(['salary', 'utilities'])
        ->and($updated->options->firstWhere('value', 'salary')->label)->toBe('Staff Salary');
});

test('tenant remains independent after school customization without propagation', function () {
    $tenant = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    $this->lifecycle->createOption($tenant, 'office', 'Office');

    $school = makeSchool('A');
    $this->lifecycle->createSchoolCustomization($school, 'expense.type', 'Expense Type', [
        ['value' => 'office', 'label' => 'Office'],
        ['value' => 'lab', 'label' => 'Lab'],
    ]);

    // Tenant later makes office required — school is NOT auto-updated
    $office = $tenant->options->firstWhere('value', 'office');
    $this->lifecycle->makeOptionRequired($office);

    $schoolDef = DynamicEnum::where('school_id', $school->id)->where('key', 'expense.type')->with('options')->first();
    expect($schoolDef->options->firstWhere('value', 'office')->is_required)->toBeFalse();
    expect($tenant->fresh()->options->firstWhere('value', 'office')->is_required)->toBeTrue();
});

test('school A and school B customizations are isolated', function () {
    $tenant = $this->lifecycle->createDefaultDefinition('expense.type', 'Expense Type');
    $this->lifecycle->createOption($tenant, 'salary', 'Salary', ['is_required' => true]);

    $schoolA = makeSchool('A');
    $schoolB = makeSchool('B');

    $customA = $this->lifecycle->createSchoolCustomization($schoolA, 'expense.type', 'Expense Type A', [
        ['value' => 'salary', 'label' => 'Salary A'],
        ['value' => 'travel', 'label' => 'Travel A'],
    ]);
    $customB = $this->lifecycle->createSchoolCustomization($schoolB, 'expense.type', 'Expense Type B', [
        ['value' => 'salary', 'label' => 'Salary B'],
        ['value' => 'utilities', 'label' => 'Utilities B'],
    ]);

    expect($customA->options->pluck('value')->all())->toBe(['salary', 'travel']);
    expect($customB->options->pluck('value')->all())->toBe(['salary', 'utilities']);

    $this->lifecycle->revertSchoolCustomization($customA);

    expect(DynamicEnum::find($customB->id))->not->toBeNull();
    expect(DynamicEnumOption::where('dynamic_enum_id', $customB->id)->count())->toBe(2);
    expect(DynamicEnum::whereNull('school_id')->where('key', 'expense.type')->exists())->toBeTrue();
});

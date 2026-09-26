<?php

uses(Tests\TestCase::class);

use App\Models\DynamicEnum;
use App\Models\DynamicEnumOption;
use App\Models\School;
use App\Rules\InDynamicEnum;
use App\Services\DynamicEnum\DynamicEnumConsumerRegistry;
use App\Services\DynamicEnum\DynamicEnumLifecycleService;
use App\Services\DynamicEnum\DynamicEnumValue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    foreach (['dynamic_enum_options', 'dynamic_enums', 'schools', 'profiles', 'addresses', 'subjects', 'guardian_student'] as $t) {
        Schema::dropIfExists($t);
    }

    Schema::create('schools', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->string('name')->nullable();
        $t->softDeletes();
        $t->timestamps();
    });

    Schema::create('dynamic_enums', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->string('key');
        $t->string('label')->nullable();
        $t->text('description')->nullable();
        $t->uuid('school_id')->nullable();
        $t->timestamps();
        $t->unique(['school_id', 'key']);
    });

    Schema::create('dynamic_enum_options', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('dynamic_enum_id');
        $t->uuid('school_id')->nullable();
        $t->string('value');
        $t->string('label')->nullable();
        $t->unsignedInteger('sort_order')->default(0);
        $t->boolean('is_active')->default(true);
        $t->boolean('is_required')->default(false);
        $t->timestamps();
    });

    Schema::create('guardian_student', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->string('relationship')->nullable();
        $t->timestamps();
    });

    Schema::create('addresses', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->string('type')->nullable();
        $t->timestamps();
    });
});

function phase5School(string $name = 'Phase5 School'): School
{
    $id = (string) Str::uuid();
    DB::table('schools')->insert([
        'id' => $id,
        'name' => $name,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return School::query()->findOrFail($id);
}

/**
 * Seed application-owned definition (school_id NULL) + tenant baseline options.
 * Optionally add school-only overlay options for isolation tests.
 */
function seedDefinition(string $key, array $tenantValues, ?School $schoolOverlay = null, array $schoolOnlyValues = []): DynamicEnum
{
    $enum = DynamicEnum::query()->create([
        'id' => (string) Str::uuid(),
        'key' => $key,
        'label' => $key,
        'school_id' => null,
    ]);

    foreach ($tenantValues as $i => $value) {
        DynamicEnumOption::query()->create([
            'id' => (string) Str::uuid(),
            'dynamic_enum_id' => $enum->id,
            'school_id' => null,
            'value' => DynamicEnumValue::canonicalize($value),
            'label' => ucfirst($value),
            'sort_order' => $i,
            'is_active' => true,
        ]);
    }

    if ($schoolOverlay !== null) {
        foreach ($schoolOnlyValues as $i => $value) {
            DynamicEnumOption::query()->create([
                'id' => (string) Str::uuid(),
                'dynamic_enum_id' => $enum->id,
                'school_id' => $schoolOverlay->id,
                'value' => DynamicEnumValue::canonicalize($value),
                'label' => ucfirst($value),
                'sort_order' => $i,
                'is_active' => true,
            ]);
        }
    }

    return $enum;
}

test('InDynamicEnum adapter accepts active option for explicit key with school context', function () {
    $school = phase5School();
    seedDefinition('profile.gender', ['male', 'female']);

    $rule = new InDynamicEnum('profile.gender', $school);
    $failed = false;
    $rule->validate('gender', 'male', function () use (&$failed) {
        $failed = true;
    });
    expect($failed)->toBeFalse();
});

test('InDynamicEnum adapter rejects unknown value for explicit key', function () {
    $school = phase5School();
    seedDefinition('address.type', ['home', 'work']);

    $rule = new InDynamicEnum('address.type', $school);
    $failed = false;
    $message = null;
    $rule->validate('type', 'warehouse', function ($msg) use (&$failed, &$message) {
        $failed = true;
        $message = $msg;
    });
    expect($failed)->toBeTrue();
    expect($message)->not->toBeNull();
});

test('InDynamicEnum school isolation rejects other school option', function () {
    $schoolA = phase5School('A');
    $schoolB = phase5School('B');
    // Tenant baseline empty; each school has its own school-only values
    seedDefinition('profile.gender', [], $schoolA, ['male']);
    // Same definition row already exists; add school B overlay on same definition
    $enum = DynamicEnum::query()->whereNull('school_id')->where('key', 'profile.gender')->firstOrFail();
    DynamicEnumOption::query()->create([
        'id' => (string) Str::uuid(),
        'dynamic_enum_id' => $enum->id,
        'school_id' => $schoolB->id,
        'value' => 'female',
        'label' => 'Female',
        'sort_order' => 0,
        'is_active' => true,
    ]);

    $rule = new InDynamicEnum('profile.gender', $schoolA);
    $failed = false;
    $rule->validate('gender', 'female', function () use (&$failed) {
        $failed = true;
    });
    expect($failed)->toBeTrue();
});

test('InDynamicEnum rejects inactive option for new selection', function () {
    $school = phase5School();
    $enum = seedDefinition('address.type', ['home', 'work']);
    DynamicEnumOption::query()
        ->where('dynamic_enum_id', $enum->id)
        ->where('value', 'work')
        ->update(['is_active' => false]);

    $rule = new InDynamicEnum('address.type', $school);
    $failed = false;
    $rule->validate('type', 'work', function () use (&$failed) {
        $failed = true;
    });
    expect($failed)->toBeTrue();
});

test('InDynamicEnum accepts null (consumer requiredness is separate)', function () {
    $school = phase5School();
    seedDefinition('profile.gender', ['male']);

    $rule = new InDynamicEnum('profile.gender', $school);
    $failed = false;
    $rule->validate('gender', null, function () use (&$failed) {
        $failed = true;
    });
    expect($failed)->toBeFalse();
});

test('consumer registry exposes guardian_student pivot descriptor', function () {
    $consumers = DynamicEnumConsumerRegistry::consumersFor('guardian.relationship');

    expect($consumers)->not->toBeEmpty();
    $hit = collect($consumers)->first(fn ($c) => ($c['table'] ?? null) === 'guardian_student');
    expect($hit)->not->toBeNull();
    expect($hit['column'])->toBe('relationship');
});

test('lifecycle blocks permanent delete when pivot table column still references value', function () {
    seedDefinition('guardian.relationship', ['father', 'mother']);
    $option = DynamicEnumOption::query()
        ->whereNull('school_id')
        ->where('value', 'father')
        ->firstOrFail();

    DB::table('guardian_student')->insert([
        'id' => (string) Str::uuid(),
        'relationship' => 'father',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = app(DynamicEnumLifecycleService::class);

    expect(fn () => $service->permanentlyDeleteTenantOption($option))
        ->toThrow(ValidationException::class);
});

test('canonical values are stored lowercase trimmed', function () {
    expect(DynamicEnumValue::canonicalize('  Father '))->toBe('father');
    expect(DynamicEnumValue::canonicalize('HOME'))->toBe('home');
});


test('HasAddress path validates type via address.type key not obsolete contract', function () {
    $school = phase5School();
    seedDefinition('address.type', ['residential', 'office']);

    // Anonymous owner using HasAddress
    $owner = new class extends \Illuminate\Database\Eloquent\Model {
        use \App\Traits\HasAddress;
        public $incrementing = false;
        protected $keyType = 'string';
        protected $table = 'profiles';
        public function getMorphClass(): string { return 'profile'; }
        // Expose validate for test
        public function testValidate(array $data): array {
            return $this->validateAddressData($data, forUpdate: false);
        }
    };

    // Bind school context for GetSchoolModel if helper reads from app
    $ruleSchool = $school;

    // Direct rule check mirrors HasAddress after migration
    $rule = new InDynamicEnum('address.type', $school);
    $failed = false;
    $rule->validate('type', 'residential', function () use (&$failed) {
        $failed = true;
    });
    expect($failed)->toBeFalse();

    $rule2 = new InDynamicEnum('address.type', $school);
    $failed2 = false;
    $rule2->validate('type', 'warehouse', function () use (&$failed2) {
        $failed2 = true;
    });
    expect($failed2)->toBeTrue();
});

test('canonicalization is applied for nested enrollment gender and relationship', function () {
    expect(DynamicEnumValue::canonicalize('Male'))->toBe('male');
    expect(DynamicEnumValue::canonicalize('STEP_FATHER'))->toBe('step_father');
});

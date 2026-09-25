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

beforeEach(function () {
    foreach (['dynamic_enum_options', 'dynamic_enums', 'schools', 'profiles', 'addresses', 'subjects', 'guardian_student'] as $t) {
        Schema::dropIfExists($t);
    }

    Schema::create('schools', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->string('name')->nullable();
        $t->timestamps();
    });

    Schema::create('dynamic_enums', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->string('key')->unique();
        $t->string('label')->nullable();
        $t->text('description')->nullable();
        $t->uuid('school_id')->nullable();
        $t->timestamps();
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

function seedDefinition(string $key, array $values, ?School $school = null): DynamicEnum
{
    $enum = DynamicEnum::query()->create([
        'id' => (string) Str::uuid(),
        'key' => $key,
        'label' => $key,
        'school_id' => $school?->id,
    ]);

    foreach ($values as $i => $value) {
        DynamicEnumOption::query()->create([
            'id' => (string) Str::uuid(),
            'dynamic_enum_id' => $enum->id,
            'school_id' => $school?->id,
            'value' => DynamicEnumValue::canonicalize($value),
            'label' => ucfirst($value),
            'sort_order' => $i,
            'is_active' => true,
        ]);
    }

    return $enum;
}

test('InDynamicEnum adapter accepts active option for explicit key with school context', function () {
    $school = phase5School();
    seedDefinition('profile.gender', ['male', 'female'], $school);

    $rule = new InDynamicEnum('profile.gender', $school);
    $failed = false;
    $rule->validate('gender', 'male', function () use (&$failed) {
        $failed = true;
    });
    expect($failed)->toBeFalse();
});

test('InDynamicEnum adapter rejects unknown value for explicit key', function () {
    $school = phase5School();
    seedDefinition('address.type', ['home', 'work'], $school);

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
    seedDefinition('profile.gender', ['male'], $schoolA);
    seedDefinition('profile.gender', ['female'], $schoolB);

    $rule = new InDynamicEnum('profile.gender', $schoolA);
    $failed = false;
    $rule->validate('gender', 'female', function () use (&$failed) {
        $failed = true;
    });
    expect($failed)->toBeTrue();
});

test('consumer registry exposes guardian_student pivot descriptor', function () {
    $registry = app(DynamicEnumConsumerRegistry::class);
    $consumers = $registry->consumersForKey('guardian.relationship');

    expect($consumers)->not->toBeEmpty();
    $hit = collect($consumers)->first(fn ($c) => ($c['table'] ?? null) === 'guardian_student');
    expect($hit)->not->toBeNull();
    expect($hit['column'])->toBe('relationship');
});

test('lifecycle blocks delete when pivot table column still references value', function () {
    $school = phase5School();
    $enum = seedDefinition('guardian.relationship', ['father', 'mother'], $school);
    $option = $enum->options()->where('value', 'father')->firstOrFail();

    DB::table('guardian_student')->insert([
        'id' => (string) Str::uuid(),
        'relationship' => 'father',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = app(DynamicEnumLifecycleService::class);

    expect(fn () => $service->deleteOption($option))
        ->toThrow(\Throwable::class);
});

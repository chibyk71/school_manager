<?php

/**
 * Dynamic Enum Phase 7 — HTTP/controller integration tests.
 *
 * Exercises the real routes → controller → administration service path so
 * controller/service contract mismatches cannot hide behind direct service tests.
 *
 * Focused schema (no RefreshDatabase), following AddressApiHttpTest conventions.
 *
 * Fixture strategy:
 * - Minimal tables for users, schools, Laratrust pivots, dynamic enums
 * - Empty Laratrust tables seeded only with the permissions under test
 * - schoolManager->setActiveSchool() for school context (production path)
 * - session cleared for tenant context
 * - Does not redesign Laratrust or add Dynamic Enum-specific auth infrastructure
 */

uses(Tests\TestCase::class);

use App\Models\DynamicEnum;
use App\Models\DynamicEnumOption;
use App\Models\School;
use App\Models\User;
use App\Services\DynamicEnum\DynamicEnumValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    config([
        'activitylog.enabled' => false,
        'app.key' => 'base64:2fl+KtvkdphvQyVfipPSU5l4l+4YfYm9d4qTjJ3b8/E=',
        // Prevent Laratrust Gate::before from treating the policy model argument as a team name
        // (permissions_as_gates would call hasPermission('viewAny', DynamicEnum::class) → SchoolSection firstOrFail).
        // Policy still runs via Gate::authorize → DynamicEnumPolicy → hasPermission(permission-name).
        'laratrust.permissions_as_gates' => false,
    ]);
    Model::unguard();
    deHttpBuildSchema();
    deHttpClearSchoolContext();
    deHttpResetGateBeforeCallbacks();

    $this->withoutVite();

    $this->withoutMiddleware([
        \App\Http\Middleware\HandleInertiaRequests::class,
        \App\Http\Middleware\EnsureCurrentSession::class,
        \App\Http\Middleware\SchoolContext::class,
        \App\Http\Middleware\CheckMaintenanceMode::class,
        \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ]);
});

afterEach(function () {
    deHttpClearSchoolContext();
    deHttpDropSchema();
});

function deHttpBuildSchema(): void
{
    foreach ([
        'permission_role', 'permission_user', 'permissions', 'role_user', 'roles',
        'school_users', 'school_sections', 'dynamic_enum_options', 'dynamic_enums',
        'schools', 'profiles', 'users',
    ] as $t) {
        Schema::dropIfExists($t);
    }

    Schema::create('users', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('username')->unique();
        $table->string('password');
        $table->boolean('is_active')->default(true);
        $table->boolean('must_change_password')->default(false);
        $table->rememberToken();
        $table->timestamps();
    });

    Schema::create('profiles', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('user_id')->nullable();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('schools', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name')->nullable();
        $table->string('code')->nullable();
        $table->string('email')->nullable();
        $table->string('type')->nullable();
        $table->boolean('is_active')->default(true);
        $table->string('slug')->nullable();
        $table->json('data')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('school_users', function (Blueprint $table) {
        $table->uuid('school_id');
        $table->uuid('user_id');
        $table->primary(['school_id', 'user_id']);
    });

    Schema::create('dynamic_enums', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('key');
        $table->string('label')->nullable();
        $table->text('description')->nullable();
        $table->uuid('school_id')->nullable();
        $table->timestamps();
        $table->unique(['school_id', 'key']);
    });

    Schema::create('dynamic_enum_options', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('dynamic_enum_id');
        $table->uuid('school_id')->nullable();
        $table->string('value');
        $table->string('label')->nullable();
        $table->unsignedInteger('sort_order')->default(0);
        $table->boolean('is_active')->default(true);
        $table->boolean('is_required')->default(false);
        $table->string('color')->nullable();
        $table->string('icon')->nullable();
        $table->timestamps();
    });

    Schema::create('school_sections', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name')->nullable();
        $table->uuid('school_id')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('roles', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name')->unique();
        $table->string('display_name')->nullable();
        $table->string('description')->nullable();
        $table->uuid('school_id')->nullable();
        $table->timestamps();
    });

    Schema::create('role_user', function (Blueprint $table) {
        $table->uuid('role_id');
        $table->uuid('user_id');
        $table->string('user_type');
        $table->string('school_section_id')->nullable();
    });

    Schema::create('permissions', function (Blueprint $table) {
        $table->id();
        $table->string('name')->unique();
        $table->string('display_name')->nullable();
        $table->string('description')->nullable();
        $table->timestamps();
    });

    Schema::create('permission_user', function (Blueprint $table) {
        $table->unsignedBigInteger('permission_id');
        $table->uuid('user_id');
        $table->string('user_type');
        $table->string('school_section_id')->nullable();
    });

    Schema::create('permission_role', function (Blueprint $table) {
        $table->unsignedBigInteger('permission_id');
        $table->uuid('role_id');
    });

    // Seed the two scope-neutral Dynamic Enum permissions once per schema.
    foreach (['dynamic-enums.view', 'dynamic-enums.manage'] as $name) {
        DB::table('permissions')->insert([
            'name' => $name,
            'display_name' => $name,
            'description' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

function deHttpDropSchema(): void
{
    foreach ([
        'permission_role', 'permission_user', 'permissions', 'role_user', 'roles',
        'school_users', 'school_sections', 'dynamic_enum_options', 'dynamic_enums',
        'schools', 'profiles', 'users',
    ] as $t) {
        Schema::dropIfExists($t);
    }
}


/**
 * Laratrust registers Gate::before when permissions_as_gates is true.
 * That callback passes the policy model argument as a team name and 404s on
 * SchoolSection. Clear before-callbacks so Gate::authorize hits DynamicEnumPolicy,
 * which calls hasPermission by permission name (null team) — the production policy path.
 */
function deHttpResetGateBeforeCallbacks(): void
{
    $gate = Gate::getFacadeRoot();
    $ref = new ReflectionObject($gate);
    $prop = $ref->getProperty('beforeCallbacks');
    $prop->setAccessible(true);
    $prop->setValue($gate, []);
}

function deHttpClearSchoolContext(): void
{
    session()->forget('active_school_id');
    try {
        $mgr = app('schoolManager');
        $ref = new ReflectionClass($mgr);
        if ($ref->hasProperty('activeSchool')) {
            $prop = $ref->getProperty('activeSchool');
            $prop->setAccessible(true);
            $prop->setValue($mgr, null);
        }
        if ($ref->hasProperty('activeSection')) {
            $prop = $ref->getProperty('activeSection');
            $prop->setAccessible(true);
            $prop->setValue($mgr, null);
        }
    } catch (Throwable) {
        // schoolManager may be unavailable in partial boots; session clear is enough
    }
}

function deHttpSetSchoolContext(School $school): void
{
    app('schoolManager')->setActiveSchool($school);
}

function deHttpSchool(string $name = 'HTTP School'): School
{
    return School::query()->create([
        'id' => (string) Str::uuid(),
        'name' => $name,
        'code' => 'HS'.Str::random(3),
        'email' => 'school@example.com',
        'type' => 'private',
        'is_active' => true,
        'slug' => Str::slug($name).'-'.Str::random(4),
    ]);
}

/**
 * Create an authenticated user with the given Dynamic Enum permission names
 * attached via the real Laratrust permission_user pivot (no Gate::before bypass).
 *
 * @param  list<string>  $permissions
 */
function deHttpUser(array $permissions = []): User
{
    $user = User::query()->create([
        'id' => (string) Str::uuid(),
        'username' => 'de_'.Str::random(8),
        'password' => bcrypt('password'),
        'is_active' => true,
        'must_change_password' => false,
    ]);

    foreach ($permissions as $name) {
        $permId = DB::table('permissions')->where('name', $name)->value('id');
        if ($permId === null) {
            $permId = DB::table('permissions')->insertGetId([
                'name' => $name,
                'display_name' => $name,
                'description' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        DB::table('permission_user')->insert([
            'permission_id' => $permId,
            'user_id' => $user->id,
            'user_type' => User::class,
            'school_section_id' => null,
        ]);
    }

    // Fresh instance so Laratrust does not see a stale permission cache.
    return User::query()->findOrFail($user->id);
}

function deHttpSeedGender(): DynamicEnum
{
    $def = DynamicEnum::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'profile.gender',
        'label' => 'Gender',
        'description' => 'Profile gender',
        'school_id' => null,
    ]);

    foreach ([['male', 'Male', 1], ['female', 'Female', 2]] as [$value, $label, $order]) {
        DynamicEnumOption::query()->create([
            'id' => (string) Str::uuid(),
            'dynamic_enum_id' => $def->id,
            'school_id' => null,
            'value' => DynamicEnumValue::canonicalize($value),
            'label' => $label,
            'sort_order' => $order,
            'is_active' => true,
            'is_required' => false,
        ]);
    }

    return $def;
}

function deHttpTenantOption(DynamicEnum $def, string $value): DynamicEnumOption
{
    return DynamicEnumOption::query()
        ->where('dynamic_enum_id', $def->id)
        ->whereNull('school_id')
        ->where('value', DynamicEnumValue::canonicalize($value))
        ->firstOrFail();
}

// ── Catalogue ──────────────────────────────────────────────────────────────

test('tenant index requires authentication', function () {
    $this->get(route('settings.system.dynamic-enums.index'))
        ->assertRedirect();
});

test('authorized user can load catalogue index as json', function () {
    deHttpSeedGender();
    $user = deHttpUser(['dynamic-enums.view']);

    $response = $this->actingAs($user)
        ->getJson(route('settings.system.dynamic-enums.index'))
        ->assertOk();

    expect($response->json('scope'))->toBe('tenant');
    expect($response->json('hasSchoolContext'))->toBeFalse();
    $keys = collect($response->json('data'))->pluck('key')->all();
    expect($keys)->toContain('profile.gender');
});

test('unauthorized user cannot view catalogue', function () {
    $user = deHttpUser([]); // no permissions

    $this->actingAs($user)
        ->getJson(route('settings.system.dynamic-enums.index'))
        ->assertForbidden();
});

test('school context catalogue returns effective options once', function () {
    $def = deHttpSeedGender();
    $school = deHttpSchool();
    $male = deHttpTenantOption($def, 'male');

    // School override of male + school-only option
    DynamicEnumOption::query()->create([
        'id' => (string) Str::uuid(),
        'dynamic_enum_id' => $def->id,
        'school_id' => $school->id,
        'value' => DynamicEnumValue::canonicalize('male'),
        'label' => 'Male (school)',
        'sort_order' => 1,
        'is_active' => true,
        'is_required' => false,
    ]);
    DynamicEnumOption::query()->create([
        'id' => (string) Str::uuid(),
        'dynamic_enum_id' => $def->id,
        'school_id' => $school->id,
        'value' => DynamicEnumValue::canonicalize('non_binary'),
        'label' => 'Non-binary',
        'sort_order' => 3,
        'is_active' => true,
        'is_required' => false,
    ]);

    $user = deHttpUser(['dynamic-enums.view']);
    deHttpSetSchoolContext($school);

    $response = $this->actingAs($user)
        ->getJson(route('settings.system.dynamic-enums.index'))
        ->assertOk();

    expect($response->json('scope'))->toBe('school');
    expect($response->json('hasSchoolContext'))->toBeTrue();

    $row = collect($response->json('data'))->firstWhere('key', 'profile.gender');
    expect($row)->not->toBeNull();
    $values = collect($row['options'])->pluck('value')->all();
    // each value once
    expect($values)->toEqualCanonicalizing(array_unique($values));
    expect($values)->toContain('male', 'female', 'non_binary');

    $sources = collect($row['options'])->pluck('source', 'value');
    expect($sources['male'])->toBe('overridden');
    expect($sources['female'])->toBe('inherited');
    expect($sources['non_binary'])->toBe('school-created');
});

// ── Detail ─────────────────────────────────────────────────────────────────

test('authorized user can view definition detail', function () {
    deHttpSeedGender();
    $user = deHttpUser(['dynamic-enums.view']);

    $this->actingAs($user)
        ->get(route('settings.system.dynamic-enums.show', 'profile.gender'))
        ->assertOk();
});

test('unknown key returns 404 on show', function () {
    $user = deHttpUser(['dynamic-enums.view']);

    $this->actingAs($user)
        ->get(route('settings.system.dynamic-enums.show', 'missing.key'))
        ->assertNotFound();
});

// ── Tenant option lifecycle ────────────────────────────────────────────────

test('tenant admin can create tenant option via HTTP', function () {
    deHttpSeedGender();
    $user = deHttpUser(['dynamic-enums.manage']);
    deHttpClearSchoolContext();

    $this->actingAs($user)
        ->from(route('settings.system.dynamic-enums.show', 'profile.gender'))
        ->post(route('settings.system.dynamic-enums.options.store', 'profile.gender'), [
            'value' => 'other',
            'label' => 'Other',
            'sort_order' => 3,
        ])
        ->assertRedirect();

    expect(
        DynamicEnumOption::query()
            ->whereNull('school_id')
            ->where('value', DynamicEnumValue::canonicalize('other'))
            ->exists()
    )->toBeTrue();
});

test('view-only user cannot create tenant option', function () {
    deHttpSeedGender();
    $user = deHttpUser(['dynamic-enums.view']);
    deHttpClearSchoolContext();

    $this->actingAs($user)
        ->post(route('settings.system.dynamic-enums.options.store', 'profile.gender'), [
            'value' => 'other',
            'label' => 'Other',
        ])
        ->assertForbidden();
});

test('tenant admin can update option presentation via HTTP', function () {
    $def = deHttpSeedGender();
    $option = deHttpTenantOption($def, 'male');
    $user = deHttpUser(['dynamic-enums.manage']);
    deHttpClearSchoolContext();

    $this->actingAs($user)
        ->from(route('settings.system.dynamic-enums.show', 'profile.gender'))
        ->patch(route('settings.system.dynamic-enums.options.update', ['key' => 'profile.gender', 'option' => $option->id]), [
            'label' => 'Male updated',
            'sort_order' => 10,
        ])
        ->assertRedirect();

    expect($option->fresh()->label)->toBe('Male updated');
    expect((int) $option->fresh()->sort_order)->toBe(10);
});

test('tenant admin can activate and deactivate via HTTP', function () {
    $def = deHttpSeedGender();
    $option = deHttpTenantOption($def, 'male');
    $user = deHttpUser(['dynamic-enums.manage']);
    deHttpClearSchoolContext();

    $this->actingAs($user)
        ->from(route('settings.system.dynamic-enums.show', 'profile.gender'))
        ->post(route('settings.system.dynamic-enums.options.deactivate', ['key' => 'profile.gender', 'option' => $option->id]))
        ->assertRedirect();

    expect($option->fresh()->is_active)->toBeFalse();

    $this->actingAs($user)
        ->from(route('settings.system.dynamic-enums.show', 'profile.gender'))
        ->post(route('settings.system.dynamic-enums.options.activate', ['key' => 'profile.gender', 'option' => $option->id]))
        ->assertRedirect();

    expect($option->fresh()->is_active)->toBeTrue();
});

test('tenant admin can make and remove required via HTTP', function () {
    $def = deHttpSeedGender();
    $option = deHttpTenantOption($def, 'male');
    $user = deHttpUser(['dynamic-enums.manage']);
    deHttpClearSchoolContext();

    $this->actingAs($user)
        ->from(route('settings.system.dynamic-enums.show', 'profile.gender'))
        ->post(route('settings.system.dynamic-enums.options.make-required', ['key' => 'profile.gender', 'option' => $option->id]))
        ->assertRedirect();

    expect($option->fresh()->is_required)->toBeTrue();

    $this->actingAs($user)
        ->from(route('settings.system.dynamic-enums.show', 'profile.gender'))
        ->post(route('settings.system.dynamic-enums.options.remove-required', ['key' => 'profile.gender', 'option' => $option->id]))
        ->assertRedirect();

    expect($option->fresh()->is_required)->toBeFalse();
});

test('school context blocks tenant requiredness mutation', function () {
    $def = deHttpSeedGender();
    $option = deHttpTenantOption($def, 'male');
    $school = deHttpSchool();
    $user = deHttpUser(['dynamic-enums.manage']);
    deHttpSetSchoolContext($school);

    $this->actingAs($user)
        ->post(route('settings.system.dynamic-enums.options.make-required', ['key' => 'profile.gender', 'option' => $option->id]))
        ->assertForbidden();

    expect($option->fresh()->is_required)->toBeFalse();
});

// ── School option lifecycle ────────────────────────────────────────────────

test('school admin can create school-only option via HTTP', function () {
    deHttpSeedGender();
    $school = deHttpSchool();
    $user = deHttpUser(['dynamic-enums.manage']);
    deHttpSetSchoolContext($school);

    $this->actingAs($user)
        ->from(route('settings.system.dynamic-enums.show', 'profile.gender'))
        ->post(route('settings.system.dynamic-enums.options.store', 'profile.gender'), [
            'value' => 'non_binary',
            'label' => 'Non-binary',
            'mode' => 'option',
        ])
        ->assertRedirect();

    $row = DynamicEnumOption::query()
        ->where('school_id', $school->id)
        ->where('value', DynamicEnumValue::canonicalize('non_binary'))
        ->first();

    expect($row)->not->toBeNull();
    expect($row->label)->toBe('Non-binary');
});

test('school admin can override tenant option and reset via HTTP', function () {
    $def = deHttpSeedGender();
    $school = deHttpSchool();
    $user = deHttpUser(['dynamic-enums.manage']);
    deHttpSetSchoolContext($school);

    $this->actingAs($user)
        ->from(route('settings.system.dynamic-enums.show', 'profile.gender'))
        ->post(route('settings.system.dynamic-enums.options.store', 'profile.gender'), [
            'value' => 'male',
            'label' => 'Male (school)',
            'mode' => 'override',
        ])
        ->assertRedirect();

    $override = DynamicEnumOption::query()
        ->where('school_id', $school->id)
        ->where('value', DynamicEnumValue::canonicalize('male'))
        ->first();

    expect($override)->not->toBeNull();
    expect($override->label)->toBe('Male (school)');

    $this->actingAs($user)
        ->from(route('settings.system.dynamic-enums.show', 'profile.gender'))
        ->post(route('settings.system.dynamic-enums.options.reset', ['key' => 'profile.gender', 'option' => $override->id]))
        ->assertRedirect();

    expect(
        DynamicEnumOption::query()->where('id', $override->id)->exists()
    )->toBeFalse();
});

test('school manage cannot mutate tenant option row', function () {
    $def = deHttpSeedGender();
    $option = deHttpTenantOption($def, 'male');
    $school = deHttpSchool();
    $user = deHttpUser(['dynamic-enums.manage']);
    deHttpSetSchoolContext($school);

    $this->actingAs($user)
        ->patch(route('settings.system.dynamic-enums.options.update', ['key' => 'profile.gender', 'option' => $option->id]), [
            'label' => 'Hijacked',
        ])
        ->assertForbidden();

    expect($option->fresh()->label)->toBe('Male');
});

test('tenant context creates tenant option even when tenant flag is false', function () {
    deHttpSeedGender();
    $user = deHttpUser(['dynamic-enums.manage']);
    deHttpClearSchoolContext();

    // Controller: wantTenant = request.tenant || school === null
    // Without school context, store always targets the tenant baseline.
    $this->actingAs($user)
        ->from(route('settings.system.dynamic-enums.show', 'profile.gender'))
        ->post(route('settings.system.dynamic-enums.options.store', 'profile.gender'), [
            'value' => 'non_binary',
            'label' => 'Non-binary',
            'mode' => 'option',
            'tenant' => false,
        ])
        ->assertRedirect();

    $row = DynamicEnumOption::query()
        ->where('value', DynamicEnumValue::canonicalize('non_binary'))
        ->first();
    expect($row)->not->toBeNull();
    expect($row->school_id)->toBeNull();
});

// ── Definition presentation ────────────────────────────────────────────────

test('definition presentation update rejects key mutation (immutable key)', function () {
    deHttpSeedGender();
    $user = deHttpUser(['dynamic-enums.manage']);
    deHttpClearSchoolContext();

    $this->actingAs($user)
        ->from(route('settings.system.dynamic-enums.show', 'profile.gender'))
        ->patch(route('settings.system.dynamic-enums.definition.update', 'profile.gender'), [
            'label' => 'Gender (tenant)',
            'description' => 'Updated',
            'key' => 'profile.hijacked',
        ])
        ->assertRedirect();

    $def = DynamicEnum::query()->whereNull('school_id')->where('key', 'profile.gender')->first();
    expect($def)->not->toBeNull();
    expect($def->label)->toBe('Gender (tenant)');
    expect($def->description)->toBe('Updated');
    expect(
        DynamicEnum::query()->where('key', 'profile.hijacked')->exists()
    )->toBeFalse();
});

// ── Consumer API ───────────────────────────────────────────────────────────

test('consumer options endpoint returns simple value/label rows', function () {
    deHttpSeedGender();
    $user = deHttpUser(['dynamic-enums.view']);

    $response = $this->actingAs($user)
        ->getJson(route('dynamic-enums.options', 'profile.gender'))
        ->assertOk()
        ->assertJsonStructure([
            'options' => [
                ['value', 'label'],
            ],
        ]);

    $values = collect($response->json('options'))->pluck('value')->all();
    expect($values)->toContain('male', 'female');
});

test('consumer options unknown key returns 404 with empty options', function () {
    $user = deHttpUser(['dynamic-enums.view']);

    $this->actingAs($user)
        ->getJson(route('dynamic-enums.options', 'missing.key'))
        ->assertNotFound()
        ->assertJson(['options' => []]);
});

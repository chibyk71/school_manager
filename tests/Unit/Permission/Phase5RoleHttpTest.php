<?php

/**
 * Permission Phase 5 — HTTP/controller authorization boundary for Roles.
 *
 * Exercises routes → RolesController → Authorization → RoleManagementService
 * so the security boundary is not only covered at the service layer.
 *
 * Fixture strategy mirrors DynamicEnumHttpTest + Phase 4 schema helpers:
 * - minimal tables for users, schools, roles, permissions, pivots
 * - direct permission_user grants (AuthorizationService path)
 * - schoolManager active school for school context
 * - no Gate::before bypass; AuthorizationService is authoritative
 */

use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Services\Permission\AuthorizationService;
use App\Services\Permission\EffectiveRoleResolver;
use App\Services\Permission\RoleCustomizationService;
use App\Services\Permission\RoleManagementService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    config([
        'activitylog.enabled' => false,
        'app.key' => 'base64:2fl+KtvkdphvQyVfipPSU5l4l+4YfYm9d4qTjJ3b8/E=',
        'laratrust.permissions_as_gates' => false,
    ]);

    Model::unguard();
    p5HttpBuildSchema();
    p5HttpClearSchoolContext();

    $this->withoutVite();
    $this->withoutMiddleware([
        \App\Http\Middleware\HandleInertiaRequests::class,
        \App\Http\Middleware\EnsureCurrentSession::class,
        \App\Http\Middleware\SchoolContext::class,
        \App\Http\Middleware\CheckMaintenanceMode::class,
        \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ]);

    // Ensure Phase 4 Authorization is the bound implementation.
    app()->instance(
        \App\Contracts\Authorization\Authorization::class,
        new AuthorizationService(new EffectiveRoleResolver())
    );
    app()->instance(
        RoleManagementService::class,
        new RoleManagementService(new EffectiveRoleResolver(), new RoleCustomizationService(new EffectiveRoleResolver()))
    );
});

afterEach(function () {
    p5HttpClearSchoolContext();
    p5HttpDropSchema();
});

function p5HttpBuildSchema(): void
{
    DB::statement('PRAGMA foreign_keys = ON');

    foreach ([
        'permission_user', 'role_user', 'permission_role', 'permissions', 'roles',
        'schools', 'users',
    ] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('users', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('username')->nullable();
        $table->string('password')->nullable();
        $table->boolean('is_active')->default(true);
        $table->boolean('must_change_password')->default(false);
        $table->timestamps();
    });

    Schema::create('schools', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('slug')->nullable();
        $table->string('email')->nullable();
        $table->string('code')->nullable();
        $table->string('type')->nullable();
        $table->boolean('is_active')->default(true);
        $table->json('data')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('roles', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('display_name')->nullable();
        $table->string('description')->nullable();
        $table->boolean('disabled')->default(false);
        $table->uuid('school_id')->nullable();
        $table->timestamps();
        $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
    });

    DB::statement('CREATE UNIQUE INDEX roles_tenant_name_unique ON roles (name) WHERE school_id IS NULL');
    DB::statement('CREATE UNIQUE INDEX roles_school_name_unique ON roles (name, school_id) WHERE school_id IS NOT NULL');

    Schema::create('permissions', function (Blueprint $table) {
        $table->id();
        $table->string('name')->unique();
        $table->string('display_name')->nullable();
        $table->string('description')->nullable();
        $table->timestamps();
    });

    Schema::create('permission_role', function (Blueprint $table) {
        $table->unsignedBigInteger('permission_id');
        $table->uuid('role_id');
        $table->primary(['permission_id', 'role_id']);
        $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
        $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
    });

    Schema::create('role_user', function (Blueprint $table) {
        $table->uuid('role_id');
        $table->uuid('user_id');
        $table->string('user_type');
        $table->uuid('school_id')->nullable();
        $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
        $table->unique(['user_id', 'role_id', 'user_type', 'school_id'], 'role_user_user_role_type_school_unique');
    });

    Schema::create('permission_user', function (Blueprint $table) {
        $table->unsignedBigInteger('permission_id');
        $table->uuid('user_id');
        $table->string('user_type');
        $table->uuid('school_id')->nullable();
        $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
        $table->unique(['user_id', 'permission_id', 'user_type', 'school_id'], 'permission_user_unique');
    });

    foreach (['roles.view', 'roles.manage'] as $name) {
        DB::table('permissions')->insert([
            'name' => $name,
            'display_name' => $name,
            'description' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

function p5HttpDropSchema(): void
{
    foreach ([
        'permission_user', 'role_user', 'permission_role', 'permissions', 'roles',
        'schools', 'users',
    ] as $table) {
        Schema::dropIfExists($table);
    }
}

function p5HttpClearSchoolContext(): void
{
    session()->forget('active_school_id');
    try {
        $mgr = app('schoolManager');
        $ref = new ReflectionClass($mgr);
        foreach (['activeSchool', 'activeSection'] as $propName) {
            if ($ref->hasProperty($propName)) {
                $prop = $ref->getProperty($propName);
                $prop->setAccessible(true);
                $prop->setValue($mgr, null);
            }
        }
    } catch (Throwable) {
        // ignore
    }
}

function p5HttpSetSchoolContext(School $school): void
{
    app('schoolManager')->setActiveSchool($school);
}

function p5HttpSchool(string $name = 'HTTP School'): School
{
    $id = (string) Str::uuid();
    DB::table('schools')->insert([
        'id' => $id,
        'name' => $name,
        'slug' => Str::slug($name).'-'.Str::random(4),
        'email' => Str::random(6).'@example.test',
        'code' => strtoupper(Str::random(4)),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return School::query()->findOrFail($id);
}

/**
 * @param  list<string>  $permissions
 */
function p5HttpUser(array $permissions = []): User
{
    $id = (string) Str::uuid();
    DB::table('users')->insert([
        'id' => $id,
        'username' => 'p5_'.Str::random(8),
        'password' => bcrypt('password'),
        'is_active' => true,
        'must_change_password' => false,
        'created_at' => now(),
        'updated_at' => now(),
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
            'user_id' => $id,
            'user_type' => User::class,
            'school_id' => null,
        ]);
    }

    return User::query()->without(['profile'])->findOrFail($id);
}

function p5HttpTenantRole(string $name, string $display = null): Role
{
    return Role::create([
        'name' => $name,
        'display_name' => $display ?? $name,
        'description' => null,
        'disabled' => false,
        'school_id' => null,
    ]);
}

function p5HttpLocalRole(School $school, string $name, string $display = null): Role
{
    return Role::create([
        'name' => $name,
        'display_name' => $display ?? $name,
        'description' => null,
        'disabled' => false,
        'school_id' => (string) $school->id,
    ]);
}

// ---------------------------------------------------------------------------
// Authorization boundary
// ---------------------------------------------------------------------------

test('user without roles.view cannot access roles index', function () {
    $user = p5HttpUser([]);

    $this->actingAs($user)
        ->getJson(route('admin.roles.index'))
        ->assertForbidden();
});

test('user with roles.view can access roles index but cannot create', function () {
    $user = p5HttpUser(['roles.view']);
    p5HttpTenantRole('teacher', 'Teacher');

    $this->actingAs($user)
        ->getJson(route('admin.roles.index'))
        ->assertOk();

    $this->actingAs($user)
        ->postJson(route('admin.roles.store'), [
            'display_name' => 'New Role',
        ])
        ->assertForbidden();
});

test('user without roles.manage cannot update status delete or sync permissions', function () {
    $user = p5HttpUser(['roles.view']);
    $role = p5HttpTenantRole('teacher', 'Teacher');

    $this->actingAs($user)
        ->putJson(route('admin.roles.update', $role), [
            'display_name' => 'Changed',
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->patchJson(route('admin.roles.status', $role), [
            'disabled' => true,
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->deleteJson(route('admin.roles.destroy', $role))
        ->assertForbidden();

    $this->actingAs($user)
        ->putJson(route('admin.roles.permissions.update', $role), [
            'permission_ids' => [],
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->postJson(route('admin.roles.status.bulk'), [
            'selection' => ['type' => 'ids', 'ids' => [(string) $role->id]],
            'action' => 'disable',
        ])
        ->assertForbidden();
});

test('user with roles.manage can create and disable a tenant role', function () {
    $user = p5HttpUser(['roles.view', 'roles.manage']);

    $create = $this->actingAs($user)
        ->postJson(route('admin.roles.store'), [
            'display_name' => 'Senior Teacher',
            'name' => 'senior_teacher',
        ]);

    $create->assertCreated();
    $roleId = $create->json('role.id');
    expect($roleId)->not->toBeEmpty();

    $this->actingAs($user)
        ->patchJson(route('admin.roles.status', $roleId), [
            'disabled' => true,
        ])
        ->assertOk();

    expect(Role::query()->find($roleId)?->disabled)->toBeTrue();
});

test('school context cannot mutate another schools local role', function () {
    $user = p5HttpUser(['roles.view', 'roles.manage']);
    $schoolA = p5HttpSchool('School A');
    $schoolB = p5HttpSchool('School B');
    $localB = p5HttpLocalRole($schoolB, 'prefect', 'Prefect');

    p5HttpSetSchoolContext($schoolA);

    $this->actingAs($user)
        ->putJson(route('admin.roles.update', $localB), [
            'display_name' => 'Hacked',
        ])
        ->assertStatus(422);

    $this->actingAs($user)
        ->patchJson(route('admin.roles.status', $localB), [
            'disabled' => true,
        ])
        ->assertStatus(422);

    $this->actingAs($user)
        ->deleteJson(route('admin.roles.destroy', $localB))
        ->assertStatus(422);

    expect($localB->fresh()->display_name)->toBe('Prefect');
    expect($localB->fresh()->disabled)->toBeFalse();
});

test('school administrator cannot delete inherited tenant role via HTTP', function () {
    $user = p5HttpUser(['roles.view', 'roles.manage']);
    $school = p5HttpSchool('School A');
    $tenant = p5HttpTenantRole('teacher', 'Teacher');

    p5HttpSetSchoolContext($school);

    $this->actingAs($user)
        ->deleteJson(route('admin.roles.destroy', $tenant))
        ->assertStatus(422);

    expect(Role::query()->whereKey($tenant->id)->exists())->toBeTrue();
});

test('permission sync is protected by roles.manage and school isolation', function () {
    $viewer = p5HttpUser(['roles.view']);
    $manager = p5HttpUser(['roles.view', 'roles.manage']);
    $schoolA = p5HttpSchool('School A');
    $schoolB = p5HttpSchool('School B');
    $localB = p5HttpLocalRole($schoolB, 'prefect', 'Prefect');

    $this->actingAs($viewer)
        ->putJson(route('admin.roles.permissions.update', $localB), [
            'permission_ids' => [],
        ])
        ->assertForbidden();

    p5HttpSetSchoolContext($schoolA);

    $this->actingAs($manager)
        ->putJson(route('admin.roles.permissions.update', $localB), [
            'permission_ids' => [],
        ])
        ->assertStatus(422);
});

test('bulk status applies to local roles and reports per-id results', function () {
    $user = p5HttpUser(['roles.view', 'roles.manage']);
    $school = p5HttpSchool('School A');
    $r1 = p5HttpLocalRole($school, 'prefect', 'Prefect');
    $r2 = p5HttpLocalRole($school, 'monitor', 'Monitor');

    p5HttpSetSchoolContext($school);

    $response = $this->actingAs($user)
        ->postJson(route('admin.roles.status.bulk'), [
            'selection' => ['type' => 'ids', 'ids' => [(string) $r1->id, (string) $r2->id]],
            'action' => 'disable',
        ])
        ->assertOk();

    expect($response->json('succeeded'))->toBe(2)
        ->and($response->json('failed'))->toBe(0);

    expect($r1->fresh()->disabled)->toBeTrue();
    expect($r2->fresh()->disabled)->toBeTrue();
});

test('bulk status mixed origins materializes inherited and updates local', function () {
    $user = p5HttpUser(['roles.view', 'roles.manage']);
    $school = p5HttpSchool('School A');
    $tenant = p5HttpTenantRole('teacher', 'Teacher');
    $local = p5HttpLocalRole($school, 'prefect', 'Prefect');

    p5HttpSetSchoolContext($school);

    $response = $this->actingAs($user)
        ->postJson(route('admin.roles.status.bulk'), [
            'selection' => ['type' => 'ids', 'ids' => [(string) $tenant->id, (string) $local->id]],
            'action' => 'disable',
        ])
        ->assertOk();

    expect($response->json('succeeded'))->toBe(2);

    // Tenant definition remains enabled; school gets a local disabled copy.
    expect($tenant->fresh()->disabled)->toBeFalse();
    expect($local->fresh()->disabled)->toBeTrue();

    $localTeacher = Role::query()
        ->forSchool((string) $school->id)
        ->where('name', 'teacher')
        ->first();
    expect($localTeacher)->not->toBeNull()
        ->and($localTeacher->disabled)->toBeTrue();
});

test('effective-role endpoints cannot bypass school scope via foreign id', function () {
    $user = p5HttpUser(['roles.view', 'roles.manage']);
    $schoolA = p5HttpSchool('School A');
    $schoolB = p5HttpSchool('School B');
    $localB = p5HttpLocalRole($schoolB, 'prefect', 'Prefect');

    p5HttpSetSchoolContext($schoolA);

    $this->actingAs($user)
        ->getJson(route('admin.roles.permissions.show', $localB))
        ->assertStatus(422);
});


test('roles index returns DataTable capabilities for bulk actions when manage is granted', function () {
    $user = p5HttpUser(['roles.view', 'roles.manage']);
    p5HttpTenantRole('teacher', 'Teacher');

    $response = $this->actingAs($user)
        ->getJson(route('admin.roles.index'))
        ->assertOk();

    $caps = $response->json('capabilities.bulkActions');
    expect($caps)->toBeArray()
        ->and(collect($caps)->pluck('id')->all())->toContain('enable', 'disable');
});

test('roles index respects search and pagination', function () {
    $user = p5HttpUser(['roles.view', 'roles.manage']);
    p5HttpTenantRole('teacher', 'Teacher');
    p5HttpTenantRole('accountant', 'Accountant');
    p5HttpTenantRole('prefect', 'Prefect');

    $search = $this->actingAs($user)
        ->getJson(route('admin.roles.index', ['search' => 'teach', 'page' => 1, 'perPage' => 10]))
        ->assertOk();

    $names = collect($search->json('data'))->pluck('name')->all();
    expect($names)->toContain('teacher')
        ->and($names)->not->toContain('accountant');

    $page = $this->actingAs($user)
        ->getJson(route('admin.roles.index', ['page' => 1, 'perPage' => 2]))
        ->assertOk();

    expect($page->json('meta.perPage'))->toBe(2)
        ->and($page->json('meta.total'))->toBeGreaterThanOrEqual(3)
        ->and($page->json('meta.lastPage'))->toBeGreaterThanOrEqual(2)
        ->and(count($page->json('data')))->toBe(2);
});

test('roles index without manage does not expose bulk capabilities', function () {
    $user = p5HttpUser(['roles.view']);
    p5HttpTenantRole('teacher', 'Teacher');

    $response = $this->actingAs($user)
        ->getJson(route('admin.roles.index'))
        ->assertOk();

    expect($response->json('capabilities.bulkActions') ?? [])->toBe([]);
});

test('bulk status with selection query resolves matching effective roles', function () {
    $user = p5HttpUser(['roles.view', 'roles.manage']);
    $school = p5HttpSchool('School A');
    p5HttpLocalRole($school, 'prefect', 'Prefect');
    p5HttpLocalRole($school, 'monitor', 'Monitor');
    p5HttpSetSchoolContext($school);

    $response = $this->actingAs($user)
        ->postJson(route('admin.roles.status.bulk'), [
            'selection' => [
                'type' => 'query',
                'query' => ['search' => 'pref'],
            ],
            'action' => 'disable',
        ])
        ->assertOk();

    expect($response->json('succeeded'))->toBe(1);
    expect(Role::query()->forSchool((string) $school->id)->where('name', 'prefect')->first()?->disabled)->toBeTrue();
    expect(Role::query()->forSchool((string) $school->id)->where('name', 'monitor')->first()?->disabled)->toBeFalse();
});


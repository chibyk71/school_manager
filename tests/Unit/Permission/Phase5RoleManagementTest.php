<?php

/**
 * Permission Phase 5 — RoleManagementService behaviour.
 */

use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Services\Permission\EffectiveRole;
use App\Services\Permission\EffectiveRoleResolver;
use App\Services\Permission\RoleCustomizationService;
use App\Services\Permission\RoleManagementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    if (! function_exists('phase5CreateSchema')) {
        function phase5CreateSchema(): void
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
                $table->timestamps();
            });

            Schema::create('schools', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('slug')->nullable();
                $table->string('email')->nullable();
                $table->string('code')->nullable();
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

            DB::statement(
                'CREATE UNIQUE INDEX roles_tenant_name_unique ON roles (name) WHERE school_id IS NULL'
            );
            DB::statement(
                'CREATE UNIQUE INDEX roles_school_name_unique ON roles (name, school_id) WHERE school_id IS NOT NULL'
            );

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
                $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
                $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
                $table->primary(['permission_id', 'role_id']);
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
        }

        function phase5MakeSchool(string $suffix = ''): School
        {
            $id = (string) Str::uuid();
            $slug = 'phase5-'.($suffix !== '' ? $suffix.'-' : '').Str::random(6);

            DB::table('schools')->insert([
                'id' => $id,
                'name' => 'Phase5 School '.$slug,
                'slug' => $slug,
                'email' => $slug.'@example.test',
                'code' => strtoupper(Str::random(4)),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return School::query()->findOrFail($id);
        }
    }

    phase5CreateSchema();
});

function phase5Service(): RoleManagementService
{
    $resolver = new EffectiveRoleResolver();
    $customization = new RoleCustomizationService($resolver);

    return new RoleManagementService($resolver, $customization);
}

test('create with explicit name', function () {
    $svc = phase5Service();
    $role = $svc->create(null, [
        'display_name' => 'Senior Teacher',
        'name' => 'senior_teacher',
    ]);

    expect($role->name)->toBe('senior_teacher')
        ->and($role->display_name)->toBe('Senior Teacher')
        ->and($role->school_id)->toBeNull()
        ->and($role->disabled)->toBeFalse();
});

test('create derives name from display_name when omitted', function () {
    $svc = phase5Service();
    $role = $svc->create(null, [
        'display_name' => 'Senior Teacher',
    ]);

    expect($role->name)->toBe('senior_teacher');
});

test('explicit duplicate name rejected in same scope', function () {
    $svc = phase5Service();
    $svc->create(null, ['display_name' => 'Teacher', 'name' => 'teacher']);

    expect(fn () => $svc->create(null, ['display_name' => 'Teacher 2', 'name' => 'teacher']))
        ->toThrow(ValidationException::class);
});

test('derived duplicate name rejected', function () {
    $svc = phase5Service();
    $svc->create(null, ['display_name' => 'Senior Teacher']);

    expect(fn () => $svc->create(null, ['display_name' => 'Senior Teacher']))
        ->toThrow(ValidationException::class);
});

test('same technical name allowed in tenant and school scopes', function () {
    $svc = phase5Service();
    $school = phase5MakeSchool('a');

    $tenant = $svc->create(null, ['display_name' => 'Teacher', 'name' => 'teacher']);
    $local = $svc->create((string) $school->id, ['display_name' => 'Teacher', 'name' => 'teacher']);

    expect($tenant->school_id)->toBeNull()
        ->and($local->school_id)->toBe((string) $school->id)
        ->and($tenant->name)->toBe($local->name);
});

test('name is immutable after create', function () {
    $svc = phase5Service();
    $role = $svc->create(null, ['display_name' => 'Teacher', 'name' => 'teacher']);

    expect(fn () => $role->update(['name' => 'other']))
        ->toThrow(RuntimeException::class);
});

test('editing local role updates local role only', function () {
    $svc = phase5Service();
    $school = phase5MakeSchool('edit-local');
    $local = $svc->create((string) $school->id, [
        'display_name' => 'Prefect',
        'name' => 'prefect',
        'description' => 'old',
    ]);

    $effective = new EffectiveRole($local, EffectiveRole::ORIGIN_LOCAL);
    $updated = $svc->updateEffective($effective, (string) $school->id, [
        'display_name' => 'Head Prefect',
        'description' => 'new',
    ]);

    expect($updated->id)->toBe($local->id)
        ->and($updated->display_name)->toBe('Head Prefect')
        ->and($updated->description)->toBe('new')
        ->and($updated->name)->toBe('prefect');
});

test('editing inherited role materializes local copy and leaves tenant unchanged', function () {
    $svc = phase5Service();
    $school = phase5MakeSchool('customize');

    $tenant = $svc->create(null, [
        'display_name' => 'Teacher',
        'name' => 'teacher',
        'description' => 'tenant desc',
    ]);
    $perm = Permission::query()->create([
        'name' => 'students.view',
        'display_name' => 'View Students',
    ]);
    $tenant->permissions()->sync([$perm->id]);

    $effective = new EffectiveRole($tenant->fresh(), EffectiveRole::ORIGIN_TENANT);
    $local = $svc->updateEffective($effective, (string) $school->id, [
        'display_name' => 'School Teacher',
        'description' => 'local desc',
    ]);

    expect($local->school_id)->toBe((string) $school->id)
        ->and($local->name)->toBe('teacher')
        ->and($local->display_name)->toBe('School Teacher')
        ->and($local->description)->toBe('local desc')
        ->and($local->permissions()->pluck('permissions.id')->all())->toContain($perm->id);

    $tenant->refresh();
    expect($tenant->display_name)->toBe('Teacher')
        ->and($tenant->description)->toBe('tenant desc');
});

test('repeated edit of customized role updates existing local role', function () {
    $svc = phase5Service();
    $school = phase5MakeSchool('repeat');

    $tenant = $svc->create(null, ['display_name' => 'Teacher', 'name' => 'teacher']);
    $effective = new EffectiveRole($tenant, EffectiveRole::ORIGIN_TENANT);
    $first = $svc->updateEffective($effective, (string) $school->id, [
        'display_name' => 'Teacher A',
    ]);
    $localEffective = new EffectiveRole($first, EffectiveRole::ORIGIN_LOCAL);
    $second = $svc->updateEffective($localEffective, (string) $school->id, [
        'display_name' => 'Teacher B',
    ]);

    expect($second->id)->toBe($first->id)
        ->and($second->display_name)->toBe('Teacher B')
        ->and(Role::query()->forSchool((string) $school->id)->where('name', 'teacher')->count())->toBe(1);
});

test('disable and enable effective local role', function () {
    $svc = phase5Service();
    $school = phase5MakeSchool('status');
    $local = $svc->create((string) $school->id, ['display_name' => 'Prefect', 'name' => 'prefect']);
    $effective = new EffectiveRole($local, EffectiveRole::ORIGIN_LOCAL);

    $disabled = $svc->disableEffective($effective, (string) $school->id);
    expect($disabled->disabled)->toBeTrue();

    $enabled = $svc->enableEffective(new EffectiveRole($disabled, EffectiveRole::ORIGIN_LOCAL), (string) $school->id);
    expect($enabled->disabled)->toBeFalse();
});

test('school cannot delete inherited tenant role', function () {
    $svc = phase5Service();
    $school = phase5MakeSchool('del-tenant');
    $tenant = $svc->create(null, ['display_name' => 'Teacher', 'name' => 'teacher']);
    $effective = new EffectiveRole($tenant, EffectiveRole::ORIGIN_TENANT);

    expect(fn () => $svc->deleteOrResetEffective($effective, (string) $school->id))
        ->toThrow(ValidationException::class);
});

test('deleting customized local role resets to tenant', function () {
    $svc = phase5Service();
    $school = phase5MakeSchool('reset');
    $tenant = $svc->create(null, ['display_name' => 'Teacher', 'name' => 'teacher']);
    $effective = new EffectiveRole($tenant, EffectiveRole::ORIGIN_TENANT);
    $local = $svc->updateEffective($effective, (string) $school->id, [
        'display_name' => 'Local Teacher',
    ]);

    $result = $svc->deleteOrResetEffective(
        new EffectiveRole($local, EffectiveRole::ORIGIN_LOCAL),
        (string) $school->id
    );

    expect($result['action'])->toBe('reset')
        ->and(Role::query()->forSchool((string) $school->id)->where('name', 'teacher')->exists())->toBeFalse()
        ->and(Role::query()->tenant()->where('name', 'teacher')->exists())->toBeTrue();
});

test('permission sync on local role', function () {
    $svc = phase5Service();
    $school = phase5MakeSchool('perms');
    $local = $svc->create((string) $school->id, ['display_name' => 'Prefect', 'name' => 'prefect']);
    $p1 = Permission::query()->create(['name' => 'a.view', 'display_name' => 'A']);
    $p2 = Permission::query()->create(['name' => 'b.view', 'display_name' => 'B']);

    $updated = $svc->syncPermissions(
        new EffectiveRole($local, EffectiveRole::ORIGIN_LOCAL),
        (string) $school->id,
        [$p1->id, $p2->id]
    );

    expect($updated->permissions()->pluck('permissions.id')->sort()->values()->all())
        ->toEqual(collect([$p1->id, $p2->id])->sort()->values()->all());
});

test('invalid permission ids rejected', function () {
    $svc = phase5Service();
    $local = $svc->create(null, ['display_name' => 'Admin', 'name' => 'admin']);

    expect(fn () => $svc->syncPermissions(
        new EffectiveRole($local, EffectiveRole::ORIGIN_TENANT),
        null,
        [999999]
    ))->toThrow(ValidationException::class);
});

test('resolveEffectiveByRoleId rejects cross-school role', function () {
    $svc = phase5Service();
    $schoolA = phase5MakeSchool('a');
    $schoolB = phase5MakeSchool('b');
    $localB = $svc->create((string) $schoolB->id, ['display_name' => 'Prefect', 'name' => 'prefect']);

    expect(fn () => $svc->resolveEffectiveByRoleId((string) $localB->id, (string) $schoolA->id))
        ->toThrow(ValidationException::class);
});

test('permission catalogue is grouped by module', function () {
    $svc = phase5Service();
    Permission::query()->create(['name' => 'students.view', 'display_name' => 'View Students']);
    Permission::query()->create(['name' => 'students.create', 'display_name' => 'Create Students']);
    Permission::query()->create(['name' => 'staff.view', 'display_name' => 'View Staff']);

    $groups = $svc->permissionCatalogueGrouped();
    $keys = collect($groups)->pluck('key')->all();

    expect($keys)->toContain('students')
        ->and($keys)->toContain('staff');
});

<?php

/**
 * Permission Phase 4 — Authorization API + capability evaluation.
 *
 * Ephemeral schema (same approach as Phases 2–3).
 */

use App\Contracts\Authorization\Authorization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Services\Permission\AuthorizationService;
use App\Services\Permission\EffectiveRoleResolver;
use App\Services\SchoolService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

function phase4CreateSchema(): void
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
        $table->unique(['user_id', 'permission_id', 'user_type', 'school_id'], 'permission_user_user_perm_type_school_unique');
    });
}

function phase4DropSchema(): void
{
    foreach ([
        'permission_user', 'role_user', 'permission_role', 'permissions', 'roles',
        'schools', 'users',
    ] as $table) {
        Schema::dropIfExists($table);
    }
}

function phase4User(string $username = 'user'): User
{
    $user = new User();
    $user->forceFill([
        'id' => (string) Str::uuid(),
        'username' => $username,
        'password' => 'secret',
    ]);
    $user->save();

    return $user->fresh();
}

function phase4School(string $name = 'School A'): School
{
    $school = new School();
    $school->forceFill([
        'id' => (string) Str::uuid(),
        'name' => $name,
        'is_active' => true,
    ]);
    $school->save();

    return $school->fresh();
}

function phase4Permission(string $name): Permission
{
    $perm = new Permission();
    $perm->forceFill([
        'name' => $name,
        'display_name' => $name,
    ]);
    $perm->save();

    return $perm->fresh();
}

function phase4Role(string $name, ?string $schoolId = null, bool $disabled = false): Role
{
    $role = new Role();
    $role->forceFill([
        'id' => (string) Str::uuid(),
        'name' => $name,
        'display_name' => $name,
        'school_id' => $schoolId,
        'disabled' => $disabled,
    ]);
    $role->save();

    return $role->fresh();
}

function phase4AttachPermissionToRole(Role $role, Permission $permission): void
{
    DB::table('permission_role')->insert([
        'permission_id' => $permission->id,
        'role_id' => $role->id,
    ]);
}

function phase4AssignRole(User $user, Role $role, ?string $schoolId): void
{
    DB::table('role_user')->insert([
        'role_id' => $role->id,
        'user_id' => $user->id,
        'user_type' => User::class,
        'school_id' => $schoolId,
    ]);
}

function phase4AssignDirectPermission(User $user, Permission $permission, ?string $schoolId): void
{
    DB::table('permission_user')->insert([
        'permission_id' => $permission->id,
        'user_id' => $user->id,
        'user_type' => User::class,
        'school_id' => $schoolId,
    ]);
}

function phase4Auth(): AuthorizationService
{
    return new AuthorizationService(new EffectiveRoleResolver());
}

beforeEach(function () {
    phase4CreateSchema();
});

afterEach(function () {
    phase4DropSchema();
});

it('binds Authorization contract to AuthorizationService', function () {
    expect(app(Authorization::class))->toBeInstanceOf(AuthorizationService::class);
});

it('allows returns bool and denies is inverse', function () {
    $auth = phase4Auth();
    $user = phase4User();
    $perm = phase4Permission('students.view');

    expect($auth->allows($user, 'students.view'))->toBeFalse()
        ->and($auth->denies($user, 'students.view'))->toBeTrue();

    phase4AssignDirectPermission($user, $perm, null);

    expect($auth->allows($user, 'students.view'))->toBeTrue()
        ->and($auth->denies($user, 'students.view'))->toBeFalse();
});

it('grants via direct tenant permission', function () {
    $auth = phase4Auth();
    $user = phase4User();
    $perm = phase4Permission('students.view');
    phase4AssignDirectPermission($user, $perm, null);

    expect($auth->allows($user, 'students.view'))->toBeTrue();
});

it('grants via direct school permission for that school only', function () {
    $auth = phase4Auth();
    $user = phase4User();
    $schoolA = phase4School('A');
    $schoolB = phase4School('B');
    $perm = phase4Permission('students.view');
    phase4AssignDirectPermission($user, $perm, $schoolA->id);

    expect($auth->allows($user, 'students.view', $schoolA->id))->toBeTrue()
        ->and($auth->allows($user, 'students.view', $schoolB->id))->toBeFalse()
        ->and($auth->allows($user, 'students.view'))->toBeFalse();
});

it('tenant direct permission applies when targeting a school', function () {
    $auth = phase4Auth();
    $user = phase4User();
    $schoolA = phase4School('A');
    $perm = phase4Permission('students.view');
    phase4AssignDirectPermission($user, $perm, null);

    expect($auth->allows($user, 'students.view', $schoolA->id))->toBeTrue();
});

it('grants via tenant role permission', function () {
    $auth = phase4Auth();
    $user = phase4User();
    $perm = phase4Permission('students.view');
    $role = phase4Role('teacher');
    phase4AttachPermissionToRole($role, $perm);
    phase4AssignRole($user, $role, null);

    expect($auth->allows($user, 'students.view'))->toBeTrue()
        ->and($auth->allows($user, 'students.view', phase4School('A')->id))->toBeTrue();
});

it('grants via school role permission for that school only', function () {
    $auth = phase4Auth();
    $user = phase4User();
    $schoolA = phase4School('A');
    $schoolB = phase4School('B');
    $perm = phase4Permission('students.view');
    $role = phase4Role('teacher', $schoolA->id);
    phase4AttachPermissionToRole($role, $perm);
    phase4AssignRole($user, $role, $schoolA->id);

    expect($auth->allows($user, 'students.view', $schoolA->id))->toBeTrue()
        ->and($auth->allows($user, 'students.view', $schoolB->id))->toBeFalse();
});

it('local role shadows tenant role permissions for the school', function () {
    $auth = phase4Auth();
    $user = phase4User();
    $schoolA = phase4School('A');

    $view = phase4Permission('students.view');
    $update = phase4Permission('students.update');

    $tenantTeacher = phase4Role('teacher');
    phase4AttachPermissionToRole($tenantTeacher, $view);

    $localTeacher = phase4Role('teacher', $schoolA->id);
    phase4AttachPermissionToRole($localTeacher, $update);

    phase4AssignRole($user, $tenantTeacher, $schoolA->id);

    expect($auth->allows($user, 'students.update', $schoolA->id))->toBeTrue()
        ->and($auth->allows($user, 'students.view', $schoolA->id))->toBeFalse();

    expect($auth->allows($user, 'students.view'))->toBeFalse();
});

it('local shadow also applies when assignment points at local role id', function () {
    $auth = phase4Auth();
    $user = phase4User();
    $schoolA = phase4School('A');

    $view = phase4Permission('students.view');
    $update = phase4Permission('students.update');

    $tenantTeacher = phase4Role('teacher');
    phase4AttachPermissionToRole($tenantTeacher, $view);

    $localTeacher = phase4Role('teacher', $schoolA->id);
    phase4AttachPermissionToRole($localTeacher, $view);
    phase4AttachPermissionToRole($localTeacher, $update);

    phase4AssignRole($user, $localTeacher, $schoolA->id);

    expect($auth->allows($user, 'students.view', $schoolA->id))->toBeTrue()
        ->and($auth->allows($user, 'students.update', $schoolA->id))->toBeTrue();
});

it('unions direct and role-derived permissions', function () {
    $auth = phase4Auth();
    $user = phase4User();
    $schoolA = phase4School('A');

    $view = phase4Permission('students.view');
    $update = phase4Permission('students.update');

    $role = phase4Role('teacher');
    phase4AttachPermissionToRole($role, $view);
    phase4AssignRole($user, $role, null);
    phase4AssignDirectPermission($user, $update, $schoolA->id);

    expect($auth->allows($user, 'students.view', $schoolA->id))->toBeTrue()
        ->and($auth->allows($user, 'students.update', $schoolA->id))->toBeTrue()
        ->and($auth->allows($user, 'students.delete', $schoolA->id))->toBeFalse();
});

it('does not leak school A capability to school B', function () {
    $auth = phase4Auth();
    $user = phase4User();
    $schoolA = phase4School('A');
    $schoolB = phase4School('B');
    $perm = phase4Permission('students.view');
    $role = phase4Role('teacher', $schoolA->id);
    phase4AttachPermissionToRole($role, $perm);
    phase4AssignRole($user, $role, $schoolA->id);

    expect($auth->allows($user, 'students.view', $schoolA->id))->toBeTrue()
        ->and($auth->allows($user, 'students.view', $schoolB->id))->toBeFalse();
});

it('disabled effective role does not grant capabilities', function () {
    $auth = phase4Auth();
    $user = phase4User();
    $schoolA = phase4School('A');
    $perm = phase4Permission('students.view');

    $tenantTeacher = phase4Role('teacher');
    phase4AttachPermissionToRole($tenantTeacher, $perm);

    phase4Role('teacher', $schoolA->id, disabled: true);

    phase4AssignRole($user, $tenantTeacher, $schoolA->id);

    expect($auth->allows($user, 'students.view', $schoolA->id))->toBeFalse();
});

it('reactivating local role restores expected capabilities', function () {
    $auth = phase4Auth();
    $user = phase4User();
    $schoolA = phase4School('A');
    $perm = phase4Permission('students.view');

    $local = phase4Role('teacher', $schoolA->id, disabled: true);
    phase4AttachPermissionToRole($local, $perm);
    phase4AssignRole($user, $local, $schoolA->id);

    expect($auth->allows($user, 'students.view', $schoolA->id))->toBeFalse();

    $local->disabled = false;
    $local->save();

    expect($auth->allows($user, 'students.view', $schoolA->id))->toBeTrue();
});

it('explicit school target does not mutate active school context', function () {
    $auth = phase4Auth();
    $user = phase4User();
    $schoolA = phase4School('A');
    $schoolB = phase4School('B');
    $perm = phase4Permission('students.view');
    phase4AssignDirectPermission($user, $perm, $schoolA->id);

    $schoolService = new SchoolService();
    app()->instance('schoolManager', $schoolService);
    $schoolService->setActiveSchool($schoolB);

    expect($schoolService->getActiveSchool()?->id)->toBe($schoolB->id);

    expect($auth->allows($user, 'students.view', $schoolA->id))->toBeTrue()
        ->and($schoolService->getActiveSchool()?->id)->toBe($schoolB->id);

    expect($auth->allows($user, 'students.view'))->toBeFalse();
});

it('current context uses active school from schoolManager', function () {
    $auth = phase4Auth();
    $user = phase4User();
    $schoolA = phase4School('A');
    $perm = phase4Permission('students.view');
    phase4AssignDirectPermission($user, $perm, $schoolA->id);

    $schoolService = new SchoolService();
    app()->instance('schoolManager', $schoolService);
    $schoolService->setActiveSchool($schoolA);

    expect($auth->allows($user, 'students.view'))->toBeTrue();
});

it('role name alone does not grant capabilities without permissions', function () {
    $auth = phase4Auth();
    $user = phase4User('admin');
    $role = phase4Role('system-admin');
    phase4AssignRole($user, $role, null);

    expect($auth->allows($user, 'students.view'))->toBeFalse()
        ->and($auth->allows($user, 'anything.goes', phase4School('X')->id))->toBeFalse();
});

it('requested wildcard pattern matches stored concrete permission name', function () {
    $auth = phase4Auth();
    $user = phase4User();
    $perm = phase4Permission('students.view');
    phase4AssignDirectPermission($user, $perm, null);

    expect($auth->allows($user, 'students.*'))->toBeTrue()
        ->and($auth->allows($user, 'students.view'))->toBeTrue();
});

it('stored wildcard permission does not reverse-match a concrete request', function () {
    $auth = phase4Auth();
    $user = phase4User();
    $perm = phase4Permission('students.*');
    phase4AssignDirectPermission($user, $perm, null);

    expect($auth->allows($user, 'students.*'))->toBeTrue()
        ->and($auth->allows($user, 'students.view'))->toBeFalse();
});

it('ignores permission_user rows for a different user_type', function () {
    $auth = phase4Auth();
    $user = phase4User();
    $perm = phase4Permission('students.view');

    DB::table('permission_user')->insert([
        'permission_id' => $perm->id,
        'user_id' => $user->id,
        'user_type' => 'App\\Models\\OtherMorph',
        'school_id' => null,
    ]);

    expect($auth->allows($user, 'students.view'))->toBeFalse();
});

it('ignores role_user rows for a different user_type', function () {
    $auth = phase4Auth();
    $user = phase4User();
    $perm = phase4Permission('students.view');
    $role = phase4Role('teacher');
    phase4AttachPermissionToRole($role, $perm);

    DB::table('role_user')->insert([
        'role_id' => $role->id,
        'user_id' => $user->id,
        'user_type' => 'App\\Models\\OtherMorph',
        'school_id' => null,
    ]);

    expect($auth->allows($user, 'students.view'))->toBeFalse();
});

it('empty permission string is denied', function () {
    $auth = phase4Auth();
    $user = phase4User();
    expect($auth->allows($user, ''))->toBeFalse()
        ->and($auth->allows($user, '   '))->toBeFalse();
});

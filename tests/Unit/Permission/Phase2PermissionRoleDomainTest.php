<?php

/**
 * Permission Phase 2 — Permission catalogue + role domain.
 *
 * Uses ephemeral schema (same approach as Phase 1) so tests do not depend on
 * full application migrate:fresh, which is blocked by an unrelated devices
 * migration issue on SQLite.
 *
 * Coverage:
 * - Permission catalogue sync (application-owned, unique names, no duplicates)
 * - Role identity (name required, unique within scope, immutable)
 * - Tenant vs school-local coexistence
 * - Presentation fields (display_name, description, presentationName fallback)
 * - Disabled state (persist, no permission loss, no delete)
 * - Role ↔ permission relationship (attach, no duplicate pivot)
 * - School integrity (FK for local roles)
 * - Regression: Laratrust permission_role / direct permission storage still works
 * - Phase 2 migration rollback integrity (fail closed on cross-scope name duplicates)
 */

use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Support\Permission\PermissionCatalogue;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Build Phase 2 schema: roles with disabled + scoped unique, permissions, pivots.
 */
function phase2CreateSchema(): void
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
        'CREATE UNIQUE INDEX roles_school_name_unique ON roles (school_id, name) WHERE school_id IS NOT NULL'
    );

    Schema::create('permissions', function (Blueprint $table) {
        $table->bigIncrements('id');
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
}

function phase2MakeSchool(string $suffix = ''): School
{
    $id = (string) Str::uuid();
    $slug = 'phase2-'.($suffix !== '' ? $suffix.'-' : '').Str::random(6);

    DB::table('schools')->insert([
        'id' => $id,
        'name' => 'Phase2 School '.$slug,
        'slug' => $slug,
        'email' => $slug.'@example.test',
        'code' => strtoupper(Str::random(4)),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return School::query()->findOrFail($id);
}

/**
 * @return array{id: string}
 */
function phase2MakeUser(): array
{
    $id = (string) Str::uuid();
    DB::table('users')->insert([
        'id' => $id,
        'username' => 'user_'.Str::random(8),
        'password' => bcrypt('password'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return ['id' => $id];
}

beforeEach(function () {
    phase2CreateSchema();
});

it('synchronizes system-defined permissions without creating duplicates', function () {
    $definitions = [
        ['name' => 'phase2.test.view', 'display_name' => 'View Phase2 Test', 'description' => 'Test permission'],
        ['name' => 'phase2.test.manage', 'display_name' => 'Manage Phase2 Test', 'description' => null],
    ];

    $first = PermissionCatalogue::sync($definitions);
    $second = PermissionCatalogue::sync($definitions);

    expect($first)->toHaveCount(2)
        ->and($second)->toHaveCount(2)
        ->and(Permission::query()->whereIn('name', ['phase2.test.view', 'phase2.test.manage'])->count())->toBe(2);

    $view = Permission::query()->where('name', 'phase2.test.view')->first();
    expect($view)->not->toBeNull()
        ->and($view->display_name)->toBe('View Phase2 Test');
});

it('rejects permission definitions without a name', function () {
    PermissionCatalogue::sync([
        ['display_name' => 'Missing name'],
    ]);
})->throws(InvalidArgumentException::class);

it('enforces unique permission names at the database level', function () {
    Permission::query()->create([
        'name' => 'phase2.unique.perm',
        'display_name' => 'One',
    ]);

    expect(fn () => Permission::query()->create([
        'name' => 'phase2.unique.perm',
        'display_name' => 'Two',
    ]))->toThrow(QueryException::class);
});

it('requires a role name', function () {
    expect(fn () => Role::query()->create([
        'name' => null,
        'school_id' => null,
    ]))->toThrow(QueryException::class);
});

it('allows the same role name in tenant and different school scopes', function () {
    $schoolA = phase2MakeSchool('a');
    $schoolB = phase2MakeSchool('b');

    $tenant = Role::query()->create([
        'name' => 'teacher',
        'display_name' => 'Teacher (Tenant)',
        'school_id' => null,
    ]);

    $localA = Role::query()->create([
        'name' => 'teacher',
        'display_name' => 'Teacher (A)',
        'school_id' => $schoolA->id,
    ]);

    $localB = Role::query()->create([
        'name' => 'teacher',
        'display_name' => 'Teacher (B)',
        'school_id' => $schoolB->id,
    ]);

    expect($tenant->isTenant())->toBeTrue()
        ->and($localA->isSchoolLocal())->toBeTrue()
        ->and($localB->isSchoolLocal())->toBeTrue()
        ->and(Role::query()->where('name', 'teacher')->count())->toBe(3);
});

it('rejects duplicate role name within the same school scope', function () {
    $school = phase2MakeSchool('dup');

    Role::query()->create([
        'name' => 'librarian',
        'school_id' => $school->id,
    ]);

    expect(fn () => Role::query()->create([
        'name' => 'librarian',
        'school_id' => $school->id,
    ]))->toThrow(QueryException::class);
});

it('rejects duplicate tenant role name when school_id is null', function () {
    Role::query()->create([
        'name' => 'accountant',
        'school_id' => null,
    ]);

    expect(fn () => Role::query()->create([
        'name' => 'accountant',
        'school_id' => null,
    ]))->toThrow(QueryException::class);
});

it('does not allow changing role name after creation', function () {
    $role = Role::query()->create([
        'name' => 'class_teacher',
        'school_id' => null,
    ]);

    $role->name = 'homeroom_teacher';
    $role->save();
})->throws(RuntimeException::class, 'Role name is immutable after creation.');

it('does not allow changing role school_id after creation', function () {
    $school = phase2MakeSchool('imm');

    $role = Role::query()->create([
        'name' => 'bursar',
        'school_id' => null,
    ]);

    $role->school_id = $school->id;
    $role->save();
})->throws(RuntimeException::class, 'Role school scope (school_id) is immutable after creation.');

it('allows display_name and description to change without changing identity', function () {
    $role = Role::query()->create([
        'name' => 'class_teacher',
        'display_name' => null,
        'description' => null,
        'school_id' => null,
    ]);

    $role->update([
        'display_name' => 'Class Teacher',
        'description' => 'Responsible for a class',
    ]);

    $role->refresh();

    expect($role->name)->toBe('class_teacher')
        ->and($role->display_name)->toBe('Class Teacher')
        ->and($role->description)->toBe('Responsible for a class')
        ->and($role->presentationName())->toBe('Class Teacher');
});

it('falls back presentation name to humanized identity when display_name is empty', function () {
    $role = Role::query()->create([
        'name' => 'vice_principal_academic',
        'display_name' => null,
        'school_id' => null,
    ]);

    expect($role->presentationName())->toBe('Vice Principal Academic');
});

it('defaults new roles to enabled', function () {
    $role = Role::query()->create([
        'name' => 'enabled_default',
        'school_id' => null,
    ]);

    $role->refresh();

    expect($role->disabled)->toBeFalse()
        ->and($role->isEnabled())->toBeTrue()
        ->and($role->isDisabled())->toBeFalse();
});

it('can disable a role without deleting it or removing permissions', function () {
    $role = Role::query()->create([
        'name' => 'to_disable',
        'school_id' => null,
        'disabled' => false,
    ]);

    $permission = Permission::query()->create([
        'name' => 'phase2.disable.check',
        'display_name' => 'Disable Check',
    ]);

    $role->permissions()->attach($permission->id);

    $role->update(['disabled' => true]);
    $role->refresh();

    expect($role->isDisabled())->toBeTrue()
        ->and(Role::query()->whereKey($role->id)->exists())->toBeTrue()
        ->and($role->permissions()->count())->toBe(1)
        ->and(Role::query()->disabled()->whereKey($role->id)->exists())->toBeTrue()
        ->and(Role::query()->enabled()->whereKey($role->id)->exists())->toBeFalse();
});

it('attaches permissions to roles and prevents duplicate pivot rows', function () {
    $role = Role::query()->create([
        'name' => 'perm_bundle',
        'school_id' => null,
    ]);

    $permission = Permission::query()->create([
        'name' => 'phase2.bundle.view',
        'display_name' => 'Bundle View',
    ]);

    $role->permissions()->attach($permission->id);
    expect($role->permissions()->count())->toBe(1);

    expect(fn () => $role->permissions()->attach($permission->id))
        ->toThrow(QueryException::class);
});

it('can detach a permission from a role', function () {
    $role = Role::query()->create([
        'name' => 'perm_detach',
        'school_id' => null,
    ]);

    $permission = Permission::query()->create([
        'name' => 'phase2.detach.view',
    ]);

    $role->permissions()->attach($permission->id);
    $role->permissions()->detach($permission->id);

    expect($role->permissions()->count())->toBe(0);
});

it('rejects local roles with invalid school references', function () {
    expect(fn () => Role::query()->create([
        'name' => 'orphan_local',
        'school_id' => (string) Str::uuid(),
    ]))->toThrow(QueryException::class);
});

it('preserves direct user permission storage with school scope', function () {
    $user = phase2MakeUser();
    $school = phase2MakeSchool('direct');

    $permission = Permission::query()->create([
        'name' => 'phase2.direct.view',
    ]);

    DB::table('permission_user')->insert([
        'permission_id' => $permission->id,
        'user_id' => $user['id'],
        'user_type' => User::class,
        'school_id' => $school->id,
    ]);

    $row = DB::table('permission_user')
        ->where('user_id', $user['id'])
        ->where('permission_id', $permission->id)
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->school_id)->toBe($school->id);

    expect(fn () => DB::table('permission_user')->insert([
        'permission_id' => $permission->id,
        'user_id' => $user['id'],
        'user_type' => User::class,
        'school_id' => $school->id,
    ]))->toThrow(QueryException::class);
});

it('preserves role assignment storage with school scope', function () {
    $user = phase2MakeUser();
    $school = phase2MakeSchool('role-assign');

    $role = Role::query()->create([
        'name' => 'phase2_assign_role',
        'school_id' => null,
    ]);

    DB::table('role_user')->insert([
        'role_id' => $role->id,
        'user_id' => $user['id'],
        'user_type' => User::class,
        'school_id' => $school->id,
    ]);

    $row = DB::table('role_user')
        ->where('user_id', $user['id'])
        ->where('role_id', $role->id)
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->school_id)->toBe($school->id);

    expect(fn () => DB::table('role_user')->insert([
        'role_id' => $role->id,
        'user_id' => $user['id'],
        'user_type' => User::class,
        'school_id' => $school->id,
    ]))->toThrow(QueryException::class);
});

/**
 * Load the Phase 2 migration class without registering it in the migrator.
 */
function phase2LoadMigration(): object
{
    $path = database_path('migrations/2026_09_30_100000_phase2_role_domain.php');

    return require $path;
}

it('refuses migration rollback when the same role name exists across scopes', function () {
    $school = phase2MakeSchool('rollback-dup');

    Role::query()->create([
        'name' => 'teacher',
        'school_id' => null,
    ]);
    Role::query()->create([
        'name' => 'teacher',
        'school_id' => $school->id,
    ]);

    $migration = phase2LoadMigration();

    expect(fn () => $migration->down())
        ->toThrow(RuntimeException::class, 'Cannot roll back Phase 2 role domain migration');

    expect(fn () => Role::query()->create([
        'name' => 'teacher',
        'school_id' => null,
    ]))->toThrow(QueryException::class);

    expect(Schema::hasColumn('roles', 'disabled'))->toBeTrue();
});

it('rolls back migration when no cross-scope name duplicates exist', function () {
    Role::query()->create([
        'name' => 'unique_only_tenant',
        'school_id' => null,
    ]);

    $school = phase2MakeSchool('rollback-ok');
    Role::query()->create([
        'name' => 'unique_only_school',
        'school_id' => $school->id,
    ]);

    $migration = phase2LoadMigration();
    $migration->down();

    expect(Schema::hasColumn('roles', 'disabled'))->toBeFalse();

    DB::table('roles')->insert([
        'id' => (string) Str::uuid(),
        'name' => 'post_rollback_name',
        'school_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => DB::table('roles')->insert([
        'id' => (string) Str::uuid(),
        'name' => 'post_rollback_name',
        'school_id' => $school->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

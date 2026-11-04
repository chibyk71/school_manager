<?php

/**
 * Permission Phase 3 — effective role resolution.
 *
 * Ephemeral schema (same approach as Phase 2) so tests do not depend on
 * full application migrate:fresh.
 */

use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Services\Permission\EffectiveRole;
use App\Services\Permission\EffectiveRoleResolver;
use App\Services\Permission\RoleCustomizationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

function phase3CreateSchema(): void
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

function phase3MakeSchool(string $suffix = ''): School
{
    $id = (string) Str::uuid();
    $slug = 'phase3-'.($suffix !== '' ? $suffix.'-' : '').Str::random(6);

    DB::table('schools')->insert([
        'id' => $id,
        'name' => 'Phase3 School '.$slug,
        'slug' => $slug,
        'email' => $slug.'@example.test',
        'code' => strtoupper(Str::random(4)),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return School::query()->findOrFail($id);
}

function phase3MakeUser(): string
{
    $id = (string) Str::uuid();
    DB::table('users')->insert([
        'id' => $id,
        'username' => 'user_'.Str::random(8),
        'password' => bcrypt('password'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

function phase3AssignRole(Role $role, string $userId, ?string $schoolId): void
{
    DB::table('role_user')->insert([
        'role_id' => $role->id,
        'user_id' => $userId,
        'user_type' => 'App\\Models\\User',
        'school_id' => $schoolId,
    ]);
}

beforeEach(function () {
    phase3CreateSchema();
});

it('includes tenant role when no local override exists', function () {
    $school = phase3MakeSchool('a');
    $tenant = Role::create([
        'name' => 'teacher',
        'display_name' => 'Teacher',
        'school_id' => null,
        'disabled' => false,
    ]);

    $resolver = new EffectiveRoleResolver;
    $effective = $resolver->resolveForSchool($school);

    expect($effective)->toHaveCount(1);
    $first = $effective->first();
    expect($first->name())->toBe('teacher')
        ->and($first->isInherited())->toBeTrue()
        ->and($first->roleId())->toBe($tenant->id)
        ->and($first->isEnabled())->toBeTrue();
});

it('local role overrides tenant role with the same name', function () {
    $school = phase3MakeSchool('a');
    $tenant = Role::create(['name' => 'teacher', 'display_name' => 'Tenant Teacher', 'school_id' => null]);
    $local = Role::create([
        'name' => 'teacher',
        'display_name' => 'Local Teacher',
        'school_id' => $school->id,
    ]);

    $resolver = new EffectiveRoleResolver;
    $effective = $resolver->resolveForSchool($school);

    expect($effective)->toHaveCount(1);
    $first = $effective->first();
    expect($first->name())->toBe('teacher')
        ->and($first->isLocal())->toBeTrue()
        ->and($first->roleId())->toBe($local->id)
        ->and($first->role->display_name)->toBe('Local Teacher');
});

it('returns exactly one effective role per name', function () {
    $school = phase3MakeSchool('a');
    Role::create(['name' => 'teacher', 'school_id' => null]);
    Role::create(['name' => 'teacher', 'school_id' => $school->id]);
    Role::create(['name' => 'bursar', 'school_id' => null]);

    $resolver = new EffectiveRoleResolver;
    $names = $resolver->resolveForSchool($school)->map->name()->all();

    expect($names)->toHaveCount(2)
        ->and($names)->toContain('teacher')
        ->and($names)->toContain('bursar')
        ->and(array_unique($names))->toHaveCount(2);
});

it('includes local-only roles with no tenant counterpart', function () {
    $school = phase3MakeSchool('a');
    Role::create(['name' => 'custom-local', 'school_id' => $school->id]);

    $resolver = new EffectiveRoleResolver;
    $effective = $resolver->resolveForSchool($school);

    expect($effective)->toHaveCount(1)
        ->and($effective->first()->name())->toBe('custom-local')
        ->and($effective->first()->isLocal())->toBeTrue();
});

it('school A cannot see school B local roles', function () {
    $schoolA = phase3MakeSchool('a');
    $schoolB = phase3MakeSchool('b');
    Role::create(['name' => 'teacher', 'school_id' => null]);
    Role::create(['name' => 'teacher', 'display_name' => 'B Local', 'school_id' => $schoolB->id]);
    Role::create(['name' => 'only-b', 'school_id' => $schoolB->id]);

    $resolver = new EffectiveRoleResolver;
    $effectiveA = $resolver->resolveForSchool($schoolA);

    $names = $effectiveA->map->name()->all();
    expect($names)->toContain('teacher')
        ->and($names)->not->toContain('only-b');

    $teacher = $effectiveA->first(fn (EffectiveRole $e) => $e->name() === 'teacher');
    expect($teacher->isInherited())->toBeTrue();
});

it('disabled tenant role remains in management catalogue', function () {
    $school = phase3MakeSchool('a');
    Role::create(['name' => 'teacher', 'school_id' => null, 'disabled' => true]);

    $resolver = new EffectiveRoleResolver;
    $management = $resolver->resolveForSchool($school);
    $assignable = $resolver->assignableForSchool($school);

    expect($management)->toHaveCount(1)
        ->and($management->first()->isDisabled())->toBeTrue()
        ->and($assignable)->toHaveCount(0);
});

it('disabled tenant role is excluded from assignment catalogue', function () {
    $school = phase3MakeSchool('a');
    Role::create(['name' => 'teacher', 'school_id' => null, 'disabled' => true]);
    Role::create(['name' => 'bursar', 'school_id' => null, 'disabled' => false]);

    $resolver = new EffectiveRoleResolver;
    $assignable = $resolver->assignableForSchool($school);

    expect($assignable)->toHaveCount(1)
        ->and($assignable->first()->name())->toBe('bursar');
});

it('enabled local role overrides disabled tenant role', function () {
    $school = phase3MakeSchool('a');
    Role::create(['name' => 'teacher', 'school_id' => null, 'disabled' => true]);
    $local = Role::create([
        'name' => 'teacher',
        'school_id' => $school->id,
        'disabled' => false,
    ]);

    $resolver = new EffectiveRoleResolver;
    $effective = $resolver->resolveForSchool($school)->first();

    expect($effective->isLocal())->toBeTrue()
        ->and($effective->isEnabled())->toBeTrue()
        ->and($effective->roleId())->toBe($local->id);

    expect($resolver->assignableForSchool($school))->toHaveCount(1);
});

it('disabled local role overrides enabled tenant role and stays disabled', function () {
    $school = phase3MakeSchool('a');
    Role::create(['name' => 'teacher', 'school_id' => null, 'disabled' => false]);
    $local = Role::create([
        'name' => 'teacher',
        'school_id' => $school->id,
        'disabled' => true,
    ]);

    $resolver = new EffectiveRoleResolver;
    $effective = $resolver->resolveForSchool($school)->first();

    expect($effective->isLocal())->toBeTrue()
        ->and($effective->isDisabled())->toBeTrue()
        ->and($effective->roleId())->toBe($local->id);

    // Must NOT fall back to enabled tenant role.
    expect($resolver->assignableForSchool($school))->toHaveCount(0);
});

it('local deletion restores tenant role as effective', function () {
    $school = phase3MakeSchool('a');
    $tenant = Role::create(['name' => 'teacher', 'display_name' => 'Tenant', 'school_id' => null]);
    $local = Role::create(['name' => 'teacher', 'display_name' => 'Local', 'school_id' => $school->id]);

    $service = new RoleCustomizationService(new EffectiveRoleResolver);
    $service->delete($local);

    $resolver = new EffectiveRoleResolver;
    $effective = $resolver->resolveForSchool($school)->first();

    expect($effective->isInherited())->toBeTrue()
        ->and($effective->roleId())->toBe($tenant->id)
        ->and($effective->role->display_name)->toBe('Tenant');
});

it('tenant deletion does not remove local same-name role', function () {
    $school = phase3MakeSchool('a');
    $tenant = Role::create(['name' => 'teacher', 'school_id' => null]);
    $local = Role::create(['name' => 'teacher', 'display_name' => 'Keep Local', 'school_id' => $school->id]);

    $service = new RoleCustomizationService(new EffectiveRoleResolver);
    $service->delete($tenant);

    expect(Role::query()->find($local->id))->not->toBeNull();

    $resolver = new EffectiveRoleResolver;
    $effective = $resolver->resolveForSchool($school)->first();
    expect($effective->isLocal())->toBeTrue()
        ->and($effective->roleId())->toBe($local->id);
});

it('resolveByName returns the effective role for a name', function () {
    $school = phase3MakeSchool('a');
    Role::create(['name' => 'teacher', 'school_id' => null]);
    $local = Role::create(['name' => 'teacher', 'school_id' => $school->id]);

    $resolver = new EffectiveRoleResolver;
    $found = $resolver->resolveByName($school, 'teacher');

    expect($found)->not->toBeNull()
        ->and($found->roleId())->toBe($local->id)
        ->and($resolver->resolveByName($school, 'missing'))->toBeNull();
});

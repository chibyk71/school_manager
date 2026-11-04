<?php

/**
 * Permission Phase 3 — role customization and assignment lifecycle.
 */

use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Services\Permission\EffectiveRoleResolver;
use App\Services\Permission\RoleCustomizationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// Reuse schema helpers from Phase3EffectiveRoleResolutionTest when loaded in same suite;
// define local copies for isolation if this file is run alone.
if (! function_exists('phase3CreateSchema')) {
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
}

beforeEach(function () {
    phase3CreateSchema();
});

it('customizes a tenant role into an independent local role', function () {
    $school = phase3MakeSchool('a');
    $tenant = Role::create([
        'name' => 'teacher',
        'display_name' => 'Teacher',
        'description' => 'Teaches classes',
        'school_id' => null,
        'disabled' => false,
    ]);

    $service = new RoleCustomizationService(new EffectiveRoleResolver);
    $local = $service->customize($tenant, $school);

    expect($local->isSchoolLocal())->toBeTrue()
        ->and($local->school_id)->toBe($school->id)
        ->and($local->name)->toBe('teacher')
        ->and($local->display_name)->toBe('Teacher')
        ->and($local->description)->toBe('Teaches classes')
        ->and($local->disabled)->toBeFalse()
        ->and($local->id)->not->toBe($tenant->id);
});

it('copies disabled state on customization', function () {
    $school = phase3MakeSchool('a');
    $tenant = Role::create([
        'name' => 'teacher',
        'school_id' => null,
        'disabled' => true,
    ]);

    $service = new RoleCustomizationService(new EffectiveRoleResolver);
    $local = $service->customize($tenant, $school);

    expect($local->disabled)->toBeTrue();
});

it('copies permission associations independently', function () {
    $school = phase3MakeSchool('a');
    $permA = Permission::create(['name' => 'phase3.a', 'display_name' => 'A']);
    $permB = Permission::create(['name' => 'phase3.b', 'display_name' => 'B']);

    $tenant = Role::create(['name' => 'teacher', 'school_id' => null]);
    $tenant->permissions()->sync([$permA->id, $permB->id]);

    $service = new RoleCustomizationService(new EffectiveRoleResolver);
    $local = $service->customize($tenant, $school);

    expect($local->permissions()->pluck('permissions.id')->sort()->values()->all())
        ->toBe([$permA->id, $permB->id]);

    // Independence: changing tenant permissions does not change local.
    $tenant->permissions()->sync([$permA->id]);
    expect($local->fresh()->permissions()->pluck('permissions.id')->sort()->values()->all())
        ->toBe([$permA->id, $permB->id]);

    // Independence: changing local permissions does not change tenant.
    $local->permissions()->sync([$permB->id]);
    expect($tenant->fresh()->permissions()->pluck('permissions.id')->all())
        ->toBe([$permA->id]);
});

it('local role has no inheritance or provenance relation to tenant', function () {
    $school = phase3MakeSchool('a');
    $tenant = Role::create([
        'name' => 'teacher',
        'display_name' => 'Teacher',
        'description' => 'Original',
        'school_id' => null,
        'disabled' => false,
    ]);

    $service = new RoleCustomizationService(new EffectiveRoleResolver);
    $local = $service->customize($tenant, $school);

    expect($local->getAttributes())->not->toHaveKey('parent_role_id')
        ->and($local->getAttributes())->not->toHaveKey('source_role_id')
        ->and($local->getAttributes())->not->toHaveKey('copied_from_role_id');

    // Tenant metadata changes after customization do not affect local.
    $tenant->display_name = 'Changed Tenant';
    $tenant->description = 'Changed';
    $tenant->disabled = true;
    $tenant->save();

    $local->refresh();
    expect($local->display_name)->toBe('Teacher')
        ->and($local->description)->toBe('Original')
        ->and($local->disabled)->toBeFalse();
});

it('rejects duplicate local role on customization', function () {
    $school = phase3MakeSchool('a');
    $tenant = Role::create(['name' => 'teacher', 'school_id' => null]);
    Role::create(['name' => 'teacher', 'school_id' => $school->id]);

    $service = new RoleCustomizationService(new EffectiveRoleResolver);

    expect(fn () => $service->customize($tenant, $school))
        ->toThrow(ValidationException::class);
});

it('rejects local role as customization source', function () {
    $school = phase3MakeSchool('a');
    $local = Role::create(['name' => 'teacher', 'school_id' => $school->id]);

    $service = new RoleCustomizationService(new EffectiveRoleResolver);

    expect(fn () => $service->customize($local, $school))
        ->toThrow(ValidationException::class);
});

it('tenant assignment in target school blocks customization', function () {
    $school = phase3MakeSchool('a');
    $tenant = Role::create(['name' => 'teacher', 'school_id' => null]);
    $userId = phase3MakeUser();
    phase3AssignRole($tenant, $userId, $school->id);

    $service = new RoleCustomizationService(new EffectiveRoleResolver);

    expect(fn () => $service->customize($tenant, $school))
        ->toThrow(ValidationException::class);
});

it('assignment in another school does not block customization', function () {
    $schoolA = phase3MakeSchool('a');
    $schoolB = phase3MakeSchool('b');
    $tenant = Role::create(['name' => 'teacher', 'school_id' => null]);
    $userId = phase3MakeUser();
    phase3AssignRole($tenant, $userId, $schoolB->id);

    $service = new RoleCustomizationService(new EffectiveRoleResolver);
    $local = $service->customize($tenant, $schoolA);

    expect($local->school_id)->toBe($schoolA->id);
});

it('allows customization when there are no assignments', function () {
    $school = phase3MakeSchool('a');
    $tenant = Role::create(['name' => 'teacher', 'school_id' => null]);

    $service = new RoleCustomizationService(new EffectiveRoleResolver);
    $local = $service->customize($tenant, $school);

    expect($local->exists)->toBeTrue();
});

it('local role with assignments cannot be deleted', function () {
    $school = phase3MakeSchool('a');
    $local = Role::create(['name' => 'teacher', 'school_id' => $school->id]);
    phase3AssignRole($local, phase3MakeUser(), $school->id);

    $service = new RoleCustomizationService(new EffectiveRoleResolver);

    expect(fn () => $service->delete($local))
        ->toThrow(ValidationException::class);
    expect(Role::query()->find($local->id))->not->toBeNull();
});

it('tenant role with assignments cannot be deleted', function () {
    $school = phase3MakeSchool('a');
    $tenant = Role::create(['name' => 'teacher', 'school_id' => null]);
    phase3AssignRole($tenant, phase3MakeUser(), $school->id);

    $service = new RoleCustomizationService(new EffectiveRoleResolver);

    expect(fn () => $service->delete($tenant))
        ->toThrow(ValidationException::class);
});

it('local same-name role does not block tenant deletion', function () {
    $school = phase3MakeSchool('a');
    $tenant = Role::create(['name' => 'teacher', 'school_id' => null]);
    $local = Role::create(['name' => 'teacher', 'school_id' => $school->id]);
    phase3AssignRole($local, phase3MakeUser(), $school->id);

    $service = new RoleCustomizationService(new EffectiveRoleResolver);
    $service->delete($tenant);

    expect(Role::query()->find($tenant->id))->toBeNull()
        ->and(Role::query()->find($local->id))->not->toBeNull();
});

it('disabling a role preserves assignments and removes from assignable catalogue', function () {
    $school = phase3MakeSchool('a');
    $tenant = Role::create(['name' => 'teacher', 'school_id' => null, 'disabled' => false]);
    $userId = phase3MakeUser();
    phase3AssignRole($tenant, $userId, $school->id);

    $service = new RoleCustomizationService(new EffectiveRoleResolver);
    $service->disable($tenant);

    expect($tenant->fresh()->disabled)->toBeTrue()
        ->and(DB::table('role_user')->where('role_id', $tenant->id)->count())->toBe(1);

    $resolver = new EffectiveRoleResolver;
    expect($resolver->resolveForSchool($school))->toHaveCount(1)
        ->and($resolver->assignableForSchool($school))->toHaveCount(0);
});

it('re-enabling restores assignability', function () {
    $school = phase3MakeSchool('a');
    $tenant = Role::create(['name' => 'teacher', 'school_id' => null, 'disabled' => true]);

    $service = new RoleCustomizationService(new EffectiveRoleResolver);
    $service->enable($tenant);

    $resolver = new EffectiveRoleResolver;
    expect($resolver->assignableForSchool($school))->toHaveCount(1)
        ->and($resolver->assignableForSchool($school)->first()->name())->toBe('teacher');
});

it('customization cannot operate against the wrong school context', function () {
    $schoolA = phase3MakeSchool('a');
    $schoolB = phase3MakeSchool('b');
    $tenant = Role::create(['name' => 'teacher', 'school_id' => null]);

    $service = new RoleCustomizationService(new EffectiveRoleResolver);
    $localA = $service->customize($tenant, $schoolA);

    expect($localA->school_id)->toBe($schoolA->id)
        ->and(Role::query()->forSchool($schoolB->id)->where('name', 'teacher')->exists())->toBeFalse();

    $resolver = new EffectiveRoleResolver;
    $effB = $resolver->resolveForSchool($schoolB)->first();
    expect($effB->isInherited())->toBeTrue();
});

/*
 * Concurrency / lifecycle invariant regression (Phase 3 review).
 *
 * Protocol: lock roles row FOR UPDATE inside the transaction, then re-check
 * assignments, then mutate. Phase 6 assignment must lock the same role row
 * before inserting role_user so a concurrent assignment cannot land between
 * check and delete/customize (ON DELETE CASCADE would otherwise wipe it).
 */

it('delete locks the role and checks assignments inside the same transaction', function () {
    $source = file_get_contents((new ReflectionClass(RoleCustomizationService::class))->getFileName());
    $start = strpos($source, 'public function delete(');
    expect($start)->not->toBeFalse();
    $chunk = substr($source, $start, 1200);

    expect($chunk)->toContain('DB::transaction')
        ->and($chunk)->toContain('lockRole')
        ->and($chunk)->toContain('hasAnyAssignments')
        ->and($chunk)->toContain('delete()');

    $lockPos = strpos($chunk, 'lockRole');
    $checkPos = strpos($chunk, 'hasAnyAssignments');
    $deletePos = strpos($chunk, '->delete()');

    expect($lockPos)->toBeLessThan($checkPos)
        ->and($checkPos)->toBeLessThan($deletePos);

    // No pre-transaction assignment check that races with concurrent inserts.
    $beforeTx = substr($chunk, 0, strpos($chunk, 'DB::transaction'));
    expect($beforeTx)->not->toContain('hasAnyAssignments');
});

it('customize locks the tenant role and checks school assignments inside the same transaction', function () {
    $source = file_get_contents((new ReflectionClass(RoleCustomizationService::class))->getFileName());
    $start = strpos($source, 'public function customize(');
    expect($start)->not->toBeFalse();
    $chunk = substr($source, $start, 2200);

    expect($chunk)->toContain('DB::transaction')
        ->and($chunk)->toContain('lockRole')
        ->and($chunk)->toContain('hasAssignmentsInSchool');

    $txPos = strpos($chunk, 'DB::transaction');
    $lockPos = strpos($chunk, 'lockRole', $txPos);
    $checkPos = strpos($chunk, 'hasAssignmentsInSchool', $txPos);

    expect($lockPos)->not->toBeFalse()
        ->and($checkPos)->not->toBeFalse()
        ->and($lockPos)->toBeLessThan($checkPos);

    // Assignment check must not run only outside the transaction.
    $beforeTx = substr($chunk, 0, $txPos);
    expect($beforeTx)->not->toContain('hasAssignmentsInSchool');
});

it('delete under role lock cannot cascade-away an assignment that follows the shared protocol', function () {
    $school = phase3MakeSchool('a');
    $role = Role::create(['name' => 'teacher', 'school_id' => null]);
    $userId = phase3MakeUser();
    $service = new RoleCustomizationService(new EffectiveRoleResolver);

    // Phase 6-style assignment: lock role, then insert role_user (serialized with delete).
    DB::transaction(function () use ($service, $role, $userId, $school) {
        $service->lockRole((string) $role->id);
        phase3AssignRole($role, $userId, $school->id);
    });

    expect(fn () => $service->delete($role->fresh()))
        ->toThrow(ValidationException::class);

    expect(Role::query()->find($role->id))->not->toBeNull()
        ->and(DB::table('role_user')->where('role_id', $role->id)->count())->toBe(1);
});

it('customize under role lock observes assignment created via the shared lock protocol', function () {
    $school = phase3MakeSchool('a');
    $tenant = Role::create(['name' => 'teacher', 'school_id' => null]);
    $userId = phase3MakeUser();
    $service = new RoleCustomizationService(new EffectiveRoleResolver);

    DB::transaction(function () use ($service, $tenant, $userId, $school) {
        $service->lockRole((string) $tenant->id);
        phase3AssignRole($tenant, $userId, $school->id);
    });

    expect(fn () => $service->customize($tenant->fresh(), $school))
        ->toThrow(ValidationException::class);

    expect(Role::query()->forSchool($school->id)->where('name', 'teacher')->exists())->toBeFalse();
});

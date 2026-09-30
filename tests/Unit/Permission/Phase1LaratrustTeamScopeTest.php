<?php

/**
 * Permission Phase 1 — Laratrust team scope foundation.
 *
 * Coverage:
 * - Team config / model resolution (School, school_id)
 * - Real migration up() path (schema + data)
 * - Orphan section_id fails closed (no escalation to global)
 * - Global vs school-scoped coexistence after migration
 * - Cross-school isolation foundation
 * - Laratrust API resolution through CustomUserChecker
 * - down() refuses irreversible rollback
 */

use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolSection;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laratrust\Models\Team as LaratrustTeam;

/**
 * Load the Phase 1 migration class without registering it in the migrator.
 */
function phase1LoadMigration(): object
{
    $path = database_path('migrations/2026_09_29_100000_migrate_laratrust_team_scope_to_school.php');

    return require $path;
}

/**
 * Build the pre-migration schema (section-scoped pivots).
 */
function phase1CreateLegacySchema(): void
{
    foreach ([
        'permission_user', 'role_user', 'permission_role', 'permissions', 'roles',
        'school_sections', 'schools', 'users',
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
        $table->boolean('is_active')->default(true);
        $table->json('data')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('school_sections', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('school_id');
        $table->string('name');
        $table->string('display_name')->nullable();
        $table->integer('sort_order')->default(10);
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('roles', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name')->unique();
        $table->string('display_name')->nullable();
        $table->uuid('school_id')->nullable();
        $table->timestamps();
    });

    Schema::create('permissions', function (Blueprint $table) {
        $table->bigIncrements('id');
        $table->string('name')->unique();
        $table->string('display_name')->nullable();
        $table->timestamps();
    });

    Schema::create('permission_role', function (Blueprint $table) {
        $table->unsignedBigInteger('permission_id');
        $table->uuid('role_id');
        $table->primary(['permission_id', 'role_id']);
    });

    Schema::create('role_user', function (Blueprint $table) {
        $table->uuid('role_id');
        $table->uuid('user_id');
        $table->string('user_type');
        $table->uuid('school_section_id')->nullable();
    });

    Schema::create('permission_user', function (Blueprint $table) {
        $table->unsignedBigInteger('permission_id');
        $table->uuid('user_id');
        $table->string('user_type');
        $table->uuid('school_section_id')->nullable();
    });
}

/**
 * Build the post-migration schema (school-scoped pivots) for Laratrust API tests.
 */
function phase1CreateModernSchema(): void
{
    foreach ([
        'permission_user', 'role_user', 'permission_role', 'permissions', 'roles',
        'school_sections', 'schools', 'profiles', 'users',
    ] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('users', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('username')->nullable();
        $table->string('password')->nullable();
        $table->timestamps();
    });

    Schema::create('profiles', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('user_id')->nullable();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('schools', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('slug')->nullable();
        $table->boolean('is_active')->default(true);
        $table->json('data')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('roles', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name')->unique();
        $table->string('display_name')->nullable();
        $table->uuid('school_id')->nullable();
        $table->timestamps();
    });

    Schema::create('permissions', function (Blueprint $table) {
        $table->bigIncrements('id');
        $table->string('name')->unique();
        $table->string('display_name')->nullable();
        $table->timestamps();
    });

    Schema::create('permission_role', function (Blueprint $table) {
        $table->unsignedBigInteger('permission_id');
        $table->uuid('role_id');
        $table->primary(['permission_id', 'role_id']);
    });

    Schema::create('role_user', function (Blueprint $table) {
        $table->uuid('role_id');
        $table->uuid('user_id');
        $table->string('user_type');
        $table->uuid('school_id')->nullable();
        $table->unique(['user_id', 'role_id', 'user_type', 'school_id'], 'role_user_user_role_type_school_unique');
    });

    Schema::create('permission_user', function (Blueprint $table) {
        $table->unsignedBigInteger('permission_id');
        $table->uuid('user_id');
        $table->string('user_type');
        $table->uuid('school_id')->nullable();
        $table->unique(['user_id', 'permission_id', 'user_type', 'school_id'], 'permission_user_unique');
    });
}

beforeEach(function () {
    Config::set('laratrust.models.team', School::class);
    Config::set('laratrust.tables.teams', 'schools');
    Config::set('laratrust.foreign_keys.team', 'school_id');
    Config::set('laratrust.models.role', Role::class);
    Config::set('laratrust.models.permission', Permission::class);
    Config::set('laratrust.models.user', User::class);
    Config::set('laratrust.user_models', ['users' => User::class]);
    Config::set('laratrust.teams.enabled', true);
    Config::set('laratrust.teams.strict_check', false);
    Config::set('laratrust.checkers.user', \App\Checkers\CustomUserChecker::class);
    Config::set('laratrust.cache.enabled', false);
});

// ── Config / model foundation ─────────────────────────────────────────────

test('Laratrust team model resolves to School', function () {
    expect(Config::get('laratrust.models.team'))->toBe(School::class);
    expect(Config::get('laratrust.tables.teams'))->toBe('schools');
    expect(Config::get('laratrust.foreign_keys.team'))->toBe('school_id');
    expect(School::modelForeignKey())->toBe('school_id');
    expect(LaratrustTeam::modelForeignKey())->toBe('school_id');
});

test('School is Laratrust Team; SchoolSection is not', function () {
    expect(is_subclass_of(School::class, LaratrustTeam::class))->toBeTrue();
    expect(is_subclass_of(SchoolSection::class, LaratrustTeam::class))->toBeFalse();
    expect(method_exists(SchoolSection::class, 'modelForeignKey'))->toBeFalse();
});

// ── Real migration up() ───────────────────────────────────────────────────

test('migration up migrates section-scoped pivots to school_id and drops school_section_id', function () {
    phase1CreateLegacySchema();

    $schoolA = (string) Str::uuid();
    $section1 = (string) Str::uuid();
    $section2 = (string) Str::uuid();
    $userId = (string) Str::uuid();
    $roleId = (string) Str::uuid();

    DB::table('schools')->insert([
        'id' => $schoolA, 'name' => 'School A', 'slug' => 'a',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('school_sections')->insert([
        ['id' => $section1, 'school_id' => $schoolA, 'name' => 'primary', 'sort_order' => 10, 'created_at' => now(), 'updated_at' => now()],
        ['id' => $section2, 'school_id' => $schoolA, 'name' => 'jss', 'sort_order' => 20, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('users')->insert(['id' => $userId, 'username' => 'u', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('roles')->insert(['id' => $roleId, 'name' => 'teacher', 'created_at' => now(), 'updated_at' => now()]);

    // Two section-scoped rows + one legitimate global row
    DB::table('role_user')->insert([
        ['role_id' => $roleId, 'user_id' => $userId, 'user_type' => User::class, 'school_section_id' => $section1],
        ['role_id' => $roleId, 'user_id' => $userId, 'user_type' => User::class, 'school_section_id' => $section2],
        ['role_id' => $roleId, 'user_id' => $userId, 'user_type' => User::class, 'school_section_id' => null],
    ]);

    $migration = phase1LoadMigration();
    $migration->up();

    expect(Schema::hasColumn('role_user', 'school_section_id'))->toBeFalse();
    expect(Schema::hasColumn('role_user', 'school_id'))->toBeTrue();

    // Global preserved
    expect(DB::table('role_user')->whereNull('school_id')->count())->toBe(1);
    // Two section rows deduped to one school-scoped row
    expect(DB::table('role_user')->where('school_id', $schoolA)->count())->toBe(1);
    expect(DB::table('role_user')->count())->toBe(2);
});

test('migration up fails closed when orphan section_id would become global', function () {
    phase1CreateLegacySchema();

    $schoolA = (string) Str::uuid();
    $section1 = (string) Str::uuid();
    $orphanSection = (string) Str::uuid(); // never inserted into school_sections
    $userId = (string) Str::uuid();
    $roleId = (string) Str::uuid();

    DB::table('schools')->insert([
        'id' => $schoolA, 'name' => 'A', 'slug' => 'a',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('school_sections')->insert([
        'id' => $section1, 'school_id' => $schoolA, 'name' => 'primary',
        'sort_order' => 10, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('users')->insert(['id' => $userId, 'username' => 'u', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('roles')->insert(['id' => $roleId, 'name' => 'teacher', 'created_at' => now(), 'updated_at' => now()]);

    // Legitimate global + orphaned section-scoped (same user+role)
    DB::table('role_user')->insert([
        ['role_id' => $roleId, 'user_id' => $userId, 'user_type' => User::class, 'school_section_id' => null],
        ['role_id' => $roleId, 'user_id' => $userId, 'user_type' => User::class, 'school_section_id' => $orphanSection],
    ]);

    $migration = phase1LoadMigration();

    expect(fn () => $migration->up())
        ->toThrow(RuntimeException::class, 'does not resolve');

    // Schema must still have the legacy column — migration aborted before drop
    expect(Schema::hasColumn('role_user', 'school_section_id'))->toBeTrue();

    // Legitimate global row must still exist and not have been deleted by dedupe
    expect(DB::table('role_user')->whereNull('school_section_id')->count())->toBe(1);
    expect(DB::table('role_user')->where('school_section_id', $orphanSection)->count())->toBe(1);
});

test('migration down refuses irreversible authorization rollback', function () {
    $migration = phase1LoadMigration();

    expect(fn () => $migration->down())
        ->toThrow(RuntimeException::class, 'Cannot safely roll back');
});

// ── Laratrust API integration ─────────────────────────────────────────────

test('Laratrust hasRole respects school scope and global fallback via CustomUserChecker', function () {
    phase1CreateModernSchema();

    $schoolAId = (string) Str::uuid();
    $schoolBId = (string) Str::uuid();
    $roleTeacherId = (string) Str::uuid();
    $roleDirectorId = (string) Str::uuid();
    $userId = (string) Str::uuid();

    DB::table('schools')->insert([
        ['id' => $schoolAId, 'name' => 'School A', 'slug' => 'a-'.Str::random(4), 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
        ['id' => $schoolBId, 'name' => 'School B', 'slug' => 'b-'.Str::random(4), 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('roles')->insert([
        ['id' => $roleTeacherId, 'name' => 'teacher', 'display_name' => 'Teacher', 'created_at' => now(), 'updated_at' => now()],
        ['id' => $roleDirectorId, 'name' => 'sport-director', 'display_name' => 'Sport Director', 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('users')->insert([
        'id' => $userId, 'username' => 'teacher1', 'password' => bcrypt('secret'), 'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('role_user')->insert([
        ['role_id' => $roleTeacherId, 'user_id' => $userId, 'user_type' => User::class, 'school_id' => $schoolAId],
        ['role_id' => $roleDirectorId, 'user_id' => $userId, 'user_type' => User::class, 'school_id' => null],
    ]);

    $user = User::query()->findOrFail($userId);
    $schoolA = School::query()->findOrFail($schoolAId);
    $schoolB = School::query()->findOrFail($schoolBId);
    $user->flushCache();

    expect($user->hasRole('teacher', $schoolA))->toBeTrue();
    expect($user->hasRole('teacher', $schoolB))->toBeFalse();
    expect($user->hasRole('sport-director', $schoolA))->toBeTrue();
    expect($user->hasRole('sport-director', $schoolB))->toBeTrue();
    expect($user->hasRole('sport-director'))->toBeTrue();
});

test('Laratrust hasPermission isolates school-scoped direct permissions', function () {
    phase1CreateModernSchema();

    $schoolAId = (string) Str::uuid();
    $schoolBId = (string) Str::uuid();
    $userId = (string) Str::uuid();

    DB::table('schools')->insert([
        ['id' => $schoolAId, 'name' => 'School A', 'slug' => 'a-'.Str::random(4), 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
        ['id' => $schoolBId, 'name' => 'School B', 'slug' => 'b-'.Str::random(4), 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
    ]);
    $permissionId = DB::table('permissions')->insertGetId([
        'name' => 'students.view', 'display_name' => 'View students',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('users')->insert([
        'id' => $userId, 'username' => 'staff1', 'password' => bcrypt('secret'), 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('permission_user')->insert([
        'permission_id' => $permissionId, 'user_id' => $userId, 'user_type' => User::class, 'school_id' => $schoolAId,
    ]);

    $user = User::query()->findOrFail($userId);
    $schoolA = School::query()->findOrFail($schoolAId);
    $schoolB = School::query()->findOrFail($schoolBId);
    $user->flushCache();

    expect($user->hasPermission('students.view', $schoolA))->toBeTrue();
    expect($user->hasPermission('students.view', $schoolB))->toBeFalse();
    // school-scoped-only permission must not leak as global when team is null
    expect($user->hasPermission('students.view'))->toBeFalse();
});

test('cross-school role isolation holds for school-scoped assignments', function () {
    phase1CreateModernSchema();

    $schoolAId = (string) Str::uuid();
    $schoolBId = (string) Str::uuid();
    $roleId = (string) Str::uuid();
    $userId = (string) Str::uuid();

    DB::table('schools')->insert([
        ['id' => $schoolAId, 'name' => 'A', 'slug' => 'a-'.Str::random(4), 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
        ['id' => $schoolBId, 'name' => 'B', 'slug' => 'b-'.Str::random(4), 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('roles')->insert([
        'id' => $roleId, 'name' => 'admin', 'display_name' => 'Admin', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('users')->insert([
        'id' => $userId, 'username' => 'admin-a', 'password' => bcrypt('secret'), 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('role_user')->insert([
        'role_id' => $roleId, 'user_id' => $userId, 'user_type' => User::class, 'school_id' => $schoolAId,
    ]);

    $user = User::query()->findOrFail($userId);
    $schoolA = School::query()->findOrFail($schoolAId);
    $schoolB = School::query()->findOrFail($schoolBId);
    $user->flushCache();

    expect($user->hasRole('admin', $schoolA))->toBeTrue();
    expect($user->hasRole('admin', $schoolB))->toBeFalse();
});

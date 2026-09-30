<?php

/**
 * Permission Phase 1 — Laratrust team scope foundation.
 */

use App\Models\School;
use App\Models\SchoolSection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laratrust\Models\Team as LaratrustTeam;

beforeEach(function () {
    Config::set('laratrust.models.team', School::class);
    Config::set('laratrust.tables.teams', 'schools');
    Config::set('laratrust.foreign_keys.team', 'school_id');
    Config::set('laratrust.teams.enabled', true);

    foreach (['permission_user', 'role_user', 'permission_role', 'permissions', 'roles', 'school_sections', 'schools', 'users'] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('users', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('username')->nullable();
        $table->timestamps();
    });

    Schema::create('schools', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('slug')->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('school_sections', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('school_id');
        $table->string('name');
        $table->integer('sort_order')->default(10);
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('roles', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name')->unique();
        $table->uuid('school_id')->nullable();
        $table->timestamps();
    });

    Schema::create('permissions', function (Blueprint $table) {
        $table->bigIncrements('id');
        $table->string('name')->unique();
        $table->timestamps();
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
});

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

test('role_user and permission_user use school_id and not school_section_id', function () {
    expect(Schema::hasColumn('role_user', 'school_id'))->toBeTrue();
    expect(Schema::hasColumn('permission_user', 'school_id'))->toBeTrue();
    expect(Schema::hasColumn('role_user', 'school_section_id'))->toBeFalse();
    expect(Schema::hasColumn('permission_user', 'school_section_id'))->toBeFalse();
});

test('tenant global and school-scoped role assignments are supported', function () {
    $schoolA = (string) Str::uuid();
    $userId = (string) Str::uuid();
    $roleId = (string) Str::uuid();

    DB::table('schools')->insert(['id' => $schoolA, 'name' => 'School A', 'slug' => 'a', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('users')->insert(['id' => $userId, 'username' => 'u1', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('roles')->insert(['id' => $roleId, 'name' => 'teacher', 'created_at' => now(), 'updated_at' => now()]);

    DB::table('role_user')->insert([
        ['role_id' => $roleId, 'user_id' => $userId, 'user_type' => 'App\\Models\\User', 'school_id' => null],
        ['role_id' => $roleId, 'user_id' => $userId, 'user_type' => 'App\\Models\\User', 'school_id' => $schoolA],
    ]);

    expect(DB::table('role_user')->whereNull('school_id')->count())->toBe(1);
    expect(DB::table('role_user')->where('school_id', $schoolA)->count())->toBe(1);
});

test('cross-school isolation foundation: School A assignment is not School B', function () {
    $schoolA = (string) Str::uuid();
    $schoolB = (string) Str::uuid();
    $userId = (string) Str::uuid();
    $roleId = (string) Str::uuid();

    DB::table('schools')->insert([
        ['id' => $schoolA, 'name' => 'A', 'slug' => 'a', 'created_at' => now(), 'updated_at' => now()],
        ['id' => $schoolB, 'name' => 'B', 'slug' => 'b', 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('users')->insert(['id' => $userId, 'username' => 'u', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('roles')->insert(['id' => $roleId, 'name' => 'admin', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('role_user')->insert([
        'role_id' => $roleId, 'user_id' => $userId, 'user_type' => 'App\\Models\\User', 'school_id' => $schoolA,
    ]);

    expect(DB::table('role_user')->where('school_id', $schoolA)->exists())->toBeTrue();
    expect(DB::table('role_user')->where('school_id', $schoolB)->exists())->toBeFalse();
});

<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ephemeral schema for File Manager Phase 1 tests.
 *
 * Full RefreshDatabase is blocked by an unrelated SQLite devices migration
 * (drop column "ip" / staff_school_id_section_id_index). Same approach as
 * DocumentationSiteTest, SettingsInfrastructureTest, and Permission HTTP tests.
 *
 * Includes empty roles/role_user so Laratrust role lookups do not 500 when
 * Gate still consults the user checker (HTTP tests also clear Gate::before).
 */
final class StorageProviderTestSchema
{
    public static function build(): void
    {
        config([
            'app.key' => config('app.key') ?: 'base64:2fl+KtvkdphvQyVfipPSU5l4l+4YfYm9d4qTjJ3b8/E=',
            'activitylog.enabled' => false,
        ]);

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON');
        }

        self::drop();

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('schools', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('slug')->nullable();
            $table->string('email')->nullable();
            $table->string('type')->default('private');
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
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->uuid('role_id');
            $table->uuid('user_id');
            $table->string('user_type');
            $table->uuid('school_id')->nullable();
            $table->unique(
                ['user_id', 'role_id', 'user_type', 'school_id'],
                'role_user_user_role_type_school_unique'
            );
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
            $table->uuid('school_id')->nullable();
            $table->unique(
                ['user_id', 'permission_id', 'user_type', 'school_id'],
                'permission_user_user_perm_type_school_unique'
            );
        });

        Schema::create('profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('storage_providers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('driver', 32);
            $table->string('ownership', 16);
            $table->uuid('school_id')->nullable();
            $table->json('config')->nullable();
            $table->text('secrets')->nullable();
            $table->boolean('enabled')->default(true);
            $table->boolean('deleted_via_school_cascade')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['ownership', 'school_id']);
            $table->index(['driver']);
            $table->index(['enabled']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('scope')->nullable()->index();
            $table->string('key');
            $table->string('label')->nullable();
            $table->string('type')->default('string');
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['scope', 'key']);
        });
    }

    public static function drop(): void
    {
        foreach ([
            'storage_providers',
            'profiles',
            'permission_user',
            'role_user',
            'permissions',
            'roles',
            'settings',
            'schools',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
}

<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Focused schema for File Manager domain and HTTP tests.
 *
 * Avoids full RefreshDatabase (blocked by unrelated devices migration on SQLite).
 */
final class FileManagerTestSchema
{
    public static function migrate(): void
    {
        Schema::dropIfExists('fm_attachments');
        Schema::dropIfExists('fm_files');
        Schema::dropIfExists('fm_folders');
        Schema::dropIfExists('fm_school_module_configurations');
        Schema::dropIfExists('storage_providers');
        Schema::dropIfExists('activity_log');
        Schema::dropIfExists('school_users');
        Schema::dropIfExists('profiles');
        Schema::dropIfExists('schools');
        Schema::dropIfExists('users');
        Schema::dropIfExists('permission_user');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            // User model stores identity on Profile; email is not a fillable column.
            $table->string('email')->nullable()->unique();
            $table->string('username')->nullable()->unique();
            $table->string('password')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // User::$with = ['profile'] — minimal table so eager load does not fail.
        Schema::create('profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('email')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('school_users', function (Blueprint $table) {
            $table->uuid('school_id');
            $table->uuid('user_id');
            $table->primary(['school_id', 'user_id']);
        });

        Schema::create('schools', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            // School model boot generates a unique slug and may write JSON data.
            $table->string('slug')->nullable();
            $table->json('data')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // School uses LogsActivity; focused schema must accept those writes.
        Schema::create('activity_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->nullableUuidMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->nullableUuidMorphs('causer', 'causer');
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();
            $table->index('log_name');
        });

        // Empty roles tables so Laratrust/CustomUserChecker role lookups do not 500.
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
        });

        Schema::create('fm_folders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ownership', 16);
            $table->uuid('school_id')->nullable();
            $table->uuid('parent_id')->nullable();
            $table->string('name');
            $table->string('module_code', 64)->nullable();
            $table->uuid('default_storage_provider_id')->nullable();
            $table->json('capabilities')->nullable();
            $table->timestamps();
        });

        Schema::create('fm_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ownership', 16);
            $table->uuid('school_id')->nullable();
            $table->uuid('folder_id')->nullable();
            $table->uuid('storage_provider_id');
            $table->string('storage_key');
            $table->string('original_filename');
            $table->string('mime_type', 255)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('checksum', 128)->nullable();
            $table->string('managed_by', 32)->default('file_manager');
            $table->string('module_code', 64)->nullable();
            $table->timestamp('trashed_at')->nullable();
            $table->uuid('original_folder_id')->nullable();
            $table->timestamp('purge_eligible_at')->nullable();
            $table->string('purge_state', 32)->nullable();
            $table->unsignedInteger('lifecycle_version')->default(0);
            $table->timestamps();
            $table->unique(['storage_provider_id', 'storage_key'], 'fm_files_provider_key_unique');
        });

        Schema::create('fm_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('file_id');
            $table->string('owner_type');
            $table->uuid('owner_id');
            $table->string('purpose', 64)->default('default');
            $table->string('lifecycle', 32)->default('module');
            $table->timestamps();
        });

        Schema::create('fm_school_module_configurations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id');
            $table->string('module_code', 64);
            $table->boolean('enabled')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'module_code'], 'fm_school_module_unique');
        });

        // Entitlement Phase 1: storage quota accounting
        Schema::dropIfExists('fm_storage_reservations');
        Schema::dropIfExists('fm_storage_accounts');

        Schema::create('fm_storage_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('scope_key', 64)->unique();
            $table->unsignedBigInteger('used_bytes')->default(0);
            $table->unsignedBigInteger('reserved_bytes')->default(0);
            $table->timestamps();
        });

        Schema::create('fm_storage_reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('scope_key', 64);
            $table->string('operation_id', 64)->unique();
            $table->unsignedBigInteger('reserved_bytes');
            $table->unsignedBigInteger('settled_bytes')->nullable();
            $table->string('state', 32);
            $table->uuid('file_id')->nullable();
            $table->boolean('usage_released')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['scope_key', 'state']);
            $table->index('file_id');
        });

        \Illuminate\Support\Facades\DB::table('fm_storage_accounts')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'scope_key' => 'tenant',
            'used_bytes' => 0,
            'reserved_bytes' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

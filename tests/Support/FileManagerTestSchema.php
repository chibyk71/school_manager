<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Focused schema for File Manager Phase 2 domain tests.
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
        Schema::dropIfExists('schools');
        Schema::dropIfExists('users');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('permission_user');

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        Schema::create('schools', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
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
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Durable storage quota accounting for File Manager (Entitlement Phase 1).
 *
 * fm_storage_accounts: one locked row per scope (Phase 1: single "tenant" scope)
 * used for concurrency-safe capacity decisions even when no files or reservations exist.
 *
 * fm_storage_reservations: outstanding and historical reservation records for
 * idempotent settlement, release, and uncertain-outcome retention.
 */
return new class extends Migration
{
    public function up(): void
    {
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
            $table->string('state', 32); // pending | settled | released | uncertain
            $table->uuid('file_id')->nullable();
            $table->boolean('usage_released')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['scope_key', 'state']);
            $table->index('file_id');
        });

        // Bootstrap the single Phase 1 tenant account so FOR UPDATE always has a row.
        DB::table('fm_storage_accounts')->insert([
            'id' => (string) Str::uuid(),
            'scope_key' => 'tenant',
            'used_bytes' => 0,
            'reserved_bytes' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('fm_storage_reservations');
        Schema::dropIfExists('fm_storage_accounts');
    }
};

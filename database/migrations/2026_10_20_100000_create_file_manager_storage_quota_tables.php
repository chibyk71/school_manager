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
 *
 * Rollout: if fm_files already exists, used_bytes is initialized to the sum of
 * size_bytes for all remaining File Manager rows (active, trashed, and
 * purge-in-progress). Confirmed permanent deletions have no row and are not
 * counted. Direct Spatie Media Library objects are not in fm_files and are
 * never included.
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

        $usedBytes = 0;
        if (Schema::hasTable('fm_files') && Schema::hasColumn('fm_files', 'size_bytes')) {
            // Quota-covered objects only: rows still present in fm_files.
            // Active + trashed + purge-in-progress continue to count per Phase 1 rules.
            $usedBytes = (int) DB::table('fm_files')->sum('size_bytes');
        }

        // Bootstrap the single Phase 1 tenant account so FOR UPDATE always has a row.
        // insertOrIgnore keeps re-runs / concurrent installers from aborting on unique scope_key.
        DB::table('fm_storage_accounts')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'scope_key' => 'tenant',
            'used_bytes' => $usedBytes,
            'reserved_bytes' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // If the account already existed (partial deploy), never overwrite a non-zero
        // ledger with a smaller backfill — only raise used_bytes when the sum of
        // existing FM files is higher than the current ledger (initial zero case).
        if ($usedBytes > 0) {
            DB::table('fm_storage_accounts')
                ->where('scope_key', 'tenant')
                ->where('used_bytes', '<', $usedBytes)
                ->update([
                    'used_bytes' => $usedBytes,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fm_storage_reservations');
        Schema::dropIfExists('fm_storage_accounts');
    }
};

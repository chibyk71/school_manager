<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * File Manager Phase 3 — trash, retention, purge recovery columns.
 *
 * Additive only. Existing rows remain active (trashed_at null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fm_files', function (Blueprint $table) {
            $table->timestamp('trashed_at')->nullable()->after('module_code');
            $table->uuid('original_folder_id')->nullable()->after('trashed_at');
            $table->timestamp('purge_eligible_at')->nullable()->after('original_folder_id');
            $table->string('purge_state', 32)->nullable()->after('purge_eligible_at');
            $table->unsignedInteger('lifecycle_version')->default(0)->after('purge_state');

            $table->index(['trashed_at']);
            $table->index(['purge_eligible_at', 'purge_state']);
            $table->index(['managed_by', 'trashed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('fm_files', function (Blueprint $table) {
            $table->dropIndex(['trashed_at']);
            $table->dropIndex(['purge_eligible_at', 'purge_state']);
            $table->dropIndex(['managed_by', 'trashed_at']);
            $table->dropColumn([
                'trashed_at',
                'original_folder_id',
                'purge_eligible_at',
                'purge_state',
                'lifecycle_version',
            ]);
        });
    }
};

<?php

/**
 * File Manager Phase 1 — Storage provider registry.
 *
 * Explicit ownership (system | school) with school association invariants:
 *   - ownership = system  → school_id MUST be NULL
 *   - ownership = school  → school_id MUST reference schools.id
 * School force-delete cascades school-owned provider rows (never nulls school_id).
 * School soft-delete soft-deletes school-owned providers via School model boot.
 *
 * Secrets are stored in an encrypted JSON column (Laravel encrypt/decrypt).
 * Non-secret connection fields live in a plain JSON config column.
 *
 * Does not alter config/filesystems.php, Media Library defaults, or legacy
 * advanced.storage settings.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_providers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('driver', 32); // local | s3
            $table->string('ownership', 16); // system | school
            $table->uuid('school_id')->nullable();
            $table->json('config')->nullable(); // non-secret connection fields
            $table->text('secrets')->nullable(); // encrypted JSON of secret fields
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // cascadeOnDelete: force-deleting a school removes its school-owned providers.
            // nullOnDelete would set school_id=NULL and violate the ownership CHECK
            // (or silently look like a system provider). Soft-deleted schools keep rows
            // until force-delete; soft-delete of providers is handled in School::boot.
            $table->foreign('school_id')
                ->references('id')
                ->on('schools')
                ->cascadeOnDelete();

            $table->index(['ownership', 'school_id']);
            $table->index(['driver']);
            $table->index(['enabled']);
        });

        // Application-level invariant is primary; DB check where the engine supports it.
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['pgsql', 'mysql', 'mariadb'], true)) {
            DB::statement("
                ALTER TABLE storage_providers
                ADD CONSTRAINT storage_providers_ownership_school_chk
                CHECK (
                    (ownership = 'system' AND school_id IS NULL)
                    OR (ownership = 'school' AND school_id IS NOT NULL)
                )
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_providers');
    }
};

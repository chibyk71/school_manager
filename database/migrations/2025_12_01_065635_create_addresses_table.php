<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Address Phase 1 — foundational schema.
 *
 * Ownership is polymorphic only (addressable_type + addressable_id).
 * No school_id / tenant_id. No soft deletes.
 *
 * Primary invariant (0..1 primary per owner):
 * - SQLite / PostgreSQL: partial unique index WHERE is_primary = true.
 * - MySQL and others: partial unique indexes are not portable; application-level
 *   transactional enforcement is deferred to Phase 2 (HasAddress / service layer).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Polymorphic owner — sole ownership relationship
            $table->uuidMorphs('addressable'); // addressable_id + addressable_type + index

            // nnjeim/world structured location (nullable; nullOnDelete)
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignId('state_id')->nullable()->constrained('states')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();

            // Free-text / human-readable parts
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('landmark')->nullable();
            $table->string('city_text')->nullable(); // free-text locality fallback when city_id absent
            $table->string('postal_code')->nullable();

            // Classification — Dynamic Enum–backed string (see DynamicEnumSeeder)
            $table->string('type')->nullable();

            // Primary flag: default false; first address is NOT auto-promoted
            $table->boolean('is_primary')->default(false);

            // Optional geolocation
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            $table->timestamps();
            // Intentionally no softDeletes / deleted_at — permanent deletion only
        });

        $this->addPrimaryUniqueIndex();
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }

    /**
     * Enforce at most one primary address per owner where the engine supports partial unique indexes.
     */
    private function addPrimaryUniqueIndex(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            // Partial unique index: only rows where is_primary is true participate.
            // SQLite stores boolean as 0/1; PostgreSQL accepts true/false.
            $predicate = $driver === 'sqlite' ? 'is_primary = 1' : 'is_primary = true';

            DB::statement(
                "CREATE UNIQUE INDEX addresses_one_primary_per_owner ON addresses (addressable_type, addressable_id) WHERE {$predicate}"
            );
        }
        // MySQL / MariaDB: no portable partial unique index. Phase 2 will enforce
        // the invariant transactionally in the capability/service layer.
    }
};

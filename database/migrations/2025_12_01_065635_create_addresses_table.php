<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Tenant scoping (null = global/shared address, rare)
            $table->foreignUuid('school_id')->nullable()->constrained('schools')->cascadeOnDelete();

            // Polymorphic: who owns this address (School, User, Student, Vehicle, ...)
            $table->uuidMorphs('addressable');  // addressable_id + addressable_type

            // Reference to nnjeim/world tables (structured hierarchy)
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignId('state_id')->nullable()->constrained('states')->nullOnDelete();
            // Optional: if you enabled cities module
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();

            // Free-text / human-readable parts (very important for Nigeria)
            $table->string('address_line_1')->nullable();          // e.g. "12 Adeola Odeku Street, Phase 1"
            $table->string('address_line_2')->nullable();          // e.g. "Lekki"
            $table->string('landmark')->nullable();                // e.g. "Opposite GTBank" – extremely common in NG
            $table->string('city_text')->nullable();               // free-text city/town/village fallback

            // Fallback / display fields (auto-fill from relationships if possible)
            $table->string('postal_code')->nullable();             // ZIP / Nigerian postal code (not always used)

            // Address classification
            $table->string('type')->nullable();                    // residential, school_campus, office, postal, temporary, billing
            $table->boolean('is_primary')->default(false);

            // Optional geo (useful for maps, bus routing)
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            // Standard timestamps + soft deletes
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};

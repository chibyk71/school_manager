<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\School;
use App\Models\Transport\Vehicle\Vehicle;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name');
            $table->string('registration_number')->unique();
            $table->string('make');
            $table->string('model');
            $table->integer('max_seating_capacity');
            $table->boolean('is_owned');
            $table->string('owner_name')->nullable();
            $table->string('owner_company_name')->nullable();
            $table->string('owner_phone')->nullable();
            $table->string('owner_email')->nullable()->email();
            $table->enum('fuel_type', ['Petrol', 'Diesel', 'Electric', 'Hybrid'])->nullable();
            $table->string('vehicle_fuel_type')->nullable()->index();
            $table->integer('max_fuel_capacity');
            $table->boolean('is_active')->default(true);
            $table->json('options')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['school_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};

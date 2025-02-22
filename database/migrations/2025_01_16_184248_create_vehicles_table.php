<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->string('name');
            $table->string('registration_number');
            $table->string('make');
            $table->string('model');
            $table->integer('max_seating_capacity');
            $table->boolean('is_owned');
            $table->string('owner_name')->nullable();
            $table->string('owner_company_name')->nullable();
            $table->string('owner_phone')->nullable();
            $table->string('owner_email')->nullable();
            $table->integer('max_fuel_capacity');
            $table->boolean('is_active')->default(true);
            $table->json('options')->nullable();
            $table->timestamps();
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

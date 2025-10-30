<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the schools table for a single-tenant system.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('code')->unique();
            $table->string('slug')->unique();
            $table->string('email')->unique();
            $table->string('phone_one')->nullable();
            $table->string('phone_two')->nullable();
            $table->string('logo')->nullable();
            $table->string('type')->default('private');
            $table->json('data')->nullable();
            $table->index('email');
            $table->index('slug');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Drops the schools table.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};

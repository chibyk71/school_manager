<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('phone_one');
            $table->string('phone_two')->nullable();
            $table->string('email')->unique();
            $table->string('logo')->nullable();
            $table->json('data')->nullable();
            $table->enum('tenancy_type', ['private', 'government', 'community'])->default('private');
            $table->uuid('parent_id')->nullable(); // For branches under a parent school
            $table->foreign('parent_id')->references('id')->on('schools')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profiles table — Person identity foundation (Phase 1).
 *
 * - date_of_birth (canonical name; not dob).
 * - email optional and not unique (Persons may share a communication email).
 * - user_id nullable unique FK (at most one Authentication Account per Person).
 * - No school_id (Person is tenant-scoped, independent of school).
 * - SoftDeletes preserved.
 * - Photo via Spatie Media Library only (no photo path column).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')
                ->nullable()
                ->unique()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('title')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('middle_name')->nullable();
            $table->string('gender')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['last_name', 'first_name']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};

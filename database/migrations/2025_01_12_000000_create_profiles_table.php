<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: 2026_01_24_create_profiles_table.php
 *
 * This migration creates the 'profiles' table, which serves as the central entity for representing individuals (people) in the system.
 * It stores shared personal information to avoid data duplication across roles (e.g., the same person as a staff in one school and a guardian in another).
 *
 * Features / Problems Solved:
 * - Centralizes person data (e.g., names, DOB, gender, contact info) at the tenant level, enabling cross-school and multi-role usage without redundancy.
 * - Supports de-duplication: One profile per real-world person, linked to multiple role records (Student, Staff, Guardian).
 * - Includes soft deletes for safe removal (e.g., archive inactive profiles without losing history).
 * - Timestamps for tracking creation/updates.
 * - Nullable fields for flexibility (e.g., middle_name, photo).
 * - Indexes on key fields (e.g., last_name, email) for efficient searches/queries.
 * - No 'school_id' foreign key, as profiles are tenant-wide (not scoped to a single school); school scoping happens in role models (e.g., Student, Staff).
 * - Prepares for traits like HasDynamicEnum (e.g., for title, gender) and HasCustomFields (extensible via dynamic columns).
 * - Photo field for storing avatar paths (integrates with file uploads in controllers).
 *
 * Fits into the User Management Module:
 * - Acts as the hub for all people-related data; profiles are created bundled with roles (e.g., via StudentController or StaffController).
 * - Links 1:1 to User for optional logins (via foreign key in users table).
 * - HasMany relationships to Student, Staff, Guardian models for role attachments.
 * - Integrates with frontend modals (e.g., ProfileFormModal.vue) for creation/editing, and data tables (e.g., ProfilesTable.vue) for listings.
 * - Ensures multi-tenant safety: While unscoped, queries can filter via role pivots (e.g., profiles with students in current school).
 * - Backward-compatible: Can migrate existing user data to profiles if needed.
 */

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('title')->nullable(); // e.g., Mr, Mrs, Miss (via HasDynamicEnum)
            $table->string('first_name');
            $table->string('last_name');
            $table->string('middle_name')->nullable();
            $table->string('gender')->nullable(); // e.g., male, female, other (via HasDynamicEnum)
            $table->date('dob')->nullable(); // Date of birth
            $table->string('phone')->nullable();
            $table->string('email')->nullable()->unique(); // Optional, if not tied to User; for non-login profiles
            $table->string('photo')->nullable(); // Path to profile photo (e.g., storage/uploads/profiles/...)
            $table->text('notes')->nullable(); // General notes about the person
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['last_name', 'first_name']); // For name-based searches
            $table->index('email'); // For quick lookups
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};

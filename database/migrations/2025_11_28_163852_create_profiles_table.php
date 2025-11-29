<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->uuidMorphs('profilable'); // points to staff, student, or guardian record
            $table->string('title')->nullable(); // Mr., Ms., Dr., etc.
            $table->string('first_name');
            $table->string('last_name');
            $table->string('middle_name')->nullable();
            $table->string('gender');
            $table->date('date_of_birth')->nullable();
            $table->string('phone')->nullable();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('profile_type')->index(); // 'staff', 'student', 'guardian'
            $table->boolean('is_primary')->default(false); // optional: main role
            $table->timestamps();
            $table->softDeletes();

            // Allow one user to have many profiles
            $table->index(['user_id', 'school_id', 'profile_type']);
            $table->index(['school_id', 'profile_type']);
            $table->index(['profilable_type', 'profilable_id']);
            $table->unique(['user_id', 'school_id', 'profile_type'], 'unique_user_school_profile_type');
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

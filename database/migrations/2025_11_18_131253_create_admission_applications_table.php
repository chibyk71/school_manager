<?php
// database/migrations/2025_11_19_100100_create_admission_applications_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('admission_session_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('school_id')->constrained()->cascadeOnDelete();

            $table->string('reference_number')->unique(); // ADM-2025-0847
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('entry_class');
            $table->enum('status', [
                'draft', 'submitted', 'paid', 'incomplete',
                'shortlisted', 'exam_passed', 'offered',
                'accepted', 'rejected', 'waiting_list', 'registered', 'active'
            ])->default('draft');

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('offered_at')->nullable();
            $table->timestamp('accepted_at')->nullable();

            $table->foreignUuid('student_id')->nullable()->constrained('students')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'status']);
            $table->index('reference_number');
            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_applications');
    }
};

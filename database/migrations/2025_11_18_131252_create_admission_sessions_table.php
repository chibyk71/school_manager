<?php
// database/migrations/2025_11_19_100000_create_admission_sessions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AdmissionSession represents one admission cycle (e.g. 2025/2026)
 * Controls fees, dates, entry classes, and public portal visibility
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g. "2025/2026 Admission Session"
            $table->year('academic_year'); // 2025
            $table->date('application_opens_at');
            $table->date('application_closes_at');
            $table->date('exam_date')->nullable();
            $table->decimal('application_fee', 10, 2)->default(0);
            $table->decimal('acceptance_fee', 10, 2)->default(0);
            $table->json('entry_classes'); // ["JSS1", "SSS1", "JSS2"]
            $table->boolean('is_active')->default(true);
            $table->boolean('require_exam')->default(true);
            $table->boolean('require_interview')->default(true);
            $table->text('instructions')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'academic_year']);
            $table->index(['school_id', 'is_active']);
            $table->index('application_opens_at', 'application_closes_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_sessions');
    }
};

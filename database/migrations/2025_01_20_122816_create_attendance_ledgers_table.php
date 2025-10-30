<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the attendance_ledgers table with school scoping, soft delete support, and polymorphic relationships.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('attendance_ledgers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignUuid('attendance_session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->uuidMorphs('attendable');
            $table->enum('status', ['present', 'absent', 'late', 'leave', 'holiday'])->default('absent');
            $table->string('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('deleted_at');
            $table->index('school_id');
            $table->index('attendance_session_id');
            $table->index(['attendable_id', 'attendable_type']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * Drops the attendance_ledgers table.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_ledgers');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the syllabus_detail_approvals table to track approval requests.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('syllabus_detail_approvals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignUuid('syllabus_detail_id')->constrained('syllabus_details')->cascadeOnDelete();
            $table->foreignUuid('requester_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignUuid('approver_id')->nullable()->constrained('staff')->onDelete('set null');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('comments')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('deleted_at');
            $table->index('school_id');
            $table->index('syllabus_detail_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Drops the syllabus_detail_approvals table.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('syllabus_detail_approvals');
    }
};

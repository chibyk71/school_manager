<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the lesson_plan_detail_approvals table to track approval requests.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('lesson_plan_detail_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('school_id')->constrained('schools')->onDelete('cascade');
            $table->foreignId('lesson_plan_detail_id')->constrained('lesson_plan_details')->onDelete('cascade');
            $table->foreignUuid('requester_id')->constrained('staff')->onDelete('cascade');
            $table->foreignUuid('approver_id')->nullable()->constrained('staff')->onDelete('set null');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('comments')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('deleted_at');
            $table->index('school_id');
            $table->index('lesson_plan_detail_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Drops the lesson_plan_detail_approvals table.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_plan_detail_approvals');
    }
};

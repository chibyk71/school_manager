<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the syllabus_approvals table to track approval requests.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('syllabus_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('syllabus_id')->constrained('syllabi')->cascadeOnDelete();
            $table->foreignUuid('requester_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignUuid('approver_id')->nullable()->constrained('staff')->onDelete('set null');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('comments')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('deleted_at');
            $table->index('school_id');
            $table->index('syllabus_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Drops the syllabus_approvals table.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('syllabus_approvals');
    }
};

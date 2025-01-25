<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->uuidMorphs('feedbackable'); // Supports feedback from multiple users (students, parents, teachers, etc.)"Complaint", "Suggestion", "Appreciation"
            $table->text('message');
            $table->string('subject');
            $table->enum('status', ['pending', 'reviewed', 'resolved'])->default('pending');
            $table->foreignUuid('handled_by')->nullable()->constrained('users'); // Who reviewed the feedback
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};

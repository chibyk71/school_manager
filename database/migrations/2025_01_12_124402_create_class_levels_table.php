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
        Schema::create('class_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete()->index();
            $table->foreignId('school_section_id')->constrained('school_sections')->cascadeOnDelete()->index();
            $table->string('name')->index();
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            $table->softDeletes(); // Added for soft deletion
            $table->timestamps();
            $table->unique(['school_id', 'school_section_id', 'name'], 'class_levels_unique'); // Unique constraint scoped to school and section
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_levels');
    }
};
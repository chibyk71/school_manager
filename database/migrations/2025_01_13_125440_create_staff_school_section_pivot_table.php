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
        Schema::create('staff_school_section_pivot', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignUuid('school_section_id')->constrained('school_sections')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['staff_id', 'school_section_id'], 'staff_section_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_school_section_pivot');
    }
};

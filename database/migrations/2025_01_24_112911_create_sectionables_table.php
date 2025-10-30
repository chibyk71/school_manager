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
        Schema::create('sectionables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_section_id')->constrained('school_sections')->cascadeOnDelete();
            $table->uuidMorphs('sectionable');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sectionables');
    }
};

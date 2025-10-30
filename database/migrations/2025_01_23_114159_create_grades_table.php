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
        Schema::create('grades', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignUuid('school_section_id')->nullable()->constrained('school_sections')->cascadeOnDelete();
            $table->string('name')->index();
            $table->string('code')->index();
            $table->integer('min_score');
            $table->integer('max_score');
            $table->text('remark')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['school_id', 'school_section_id', 'code'], 'grades_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};

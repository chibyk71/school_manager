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
        Schema::create('student_class_section_pivot', function (Blueprint $table) {
            $table->id();
            $table->string('student_id')->index();
            $table->foreignId('class_section_id')->constrained()->cascadeOnDelete();

            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->unique(["student_id", "class_section_id"]);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_class_section_pivot');
    }
};

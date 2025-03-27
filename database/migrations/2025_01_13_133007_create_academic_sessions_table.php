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
        Schema::create('academic_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false);
            $table->foreignUuid('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['name', 'school_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_sessions');
    }
};

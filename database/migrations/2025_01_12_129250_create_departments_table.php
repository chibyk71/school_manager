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
        Schema::create('departments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name');
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->date('effective_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['school_id', 'name'], 'departments_school_name_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};

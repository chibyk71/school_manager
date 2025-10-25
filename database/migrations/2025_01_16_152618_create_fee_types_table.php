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
        Schema::create('fee_types', function (Blueprint $table) {
            $table->id()->comment('Primary key for the fee type');
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade')->comment('The school associated with this fee type');
            $table->string('name')->comment('Name of the fee type (e.g., Tuition, Sports)');
            $table->string('description')->nullable()->comment('Optional description of the fee type');
            $table->string('color')->nullable()->comment('Color code for UI display (e.g., #FF0000)');
            $table->json('options')->nullable()->comment('Additional configuration options for the fee type');
            $table->timestamps();
            $table->softDeletes()->comment('Soft delete column for recoverable deletion');
            $table->index(['school_id', 'name'])->comment('Index for efficient querying by school and name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_types');
    }
};
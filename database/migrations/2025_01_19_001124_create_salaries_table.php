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
        Schema::create('salaries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignUuid('department_role_id')->constrained('department_role')->cascadeOnDelete();
            $table->decimal('base_salary', 15, 2);
            $table->date('effective_date');
            $table->json('options')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['school_id', 'department_role_id', 'effective_date'], 'salaries_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salaries');
    }
};

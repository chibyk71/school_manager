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
        Schema::create('salary_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete()->index();
            $table->foreignId('salary_id')->constrained('salaries')->cascadeOnDelete()->index();
            $table->foreignId('department_role_id')->constrained('department_roles')->cascadeOnDelete()->index();
            $table->decimal('amount', 15, 2);
            $table->string('currency')->default('NGN');
            $table->date('effective_date')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['school_id', 'salary_id', 'department_role_id', 'name', 'effective_date'], 'salary_structures_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_structures');
    }
};
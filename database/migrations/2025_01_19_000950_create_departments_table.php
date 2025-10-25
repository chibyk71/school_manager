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
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete()->index();
            $table->string('name');
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->date('effective_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['school_id', 'name'], 'departments_school_name_unique');
        });

        Schema::create('department_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete()->index();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete()->index();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->index();
            $table->timestamps();
            $table->unique(['department_id', 'user_id'], 'department_user_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('department_user');
        Schema::dropIfExists('departments');
    }
};

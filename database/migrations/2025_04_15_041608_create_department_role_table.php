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
        Schema::create('department_role', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete()->index();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete()->index();
            $table->foreignUuid('role_id')->constrained('roles')->cascadeOnDelete()->index();
            $table->foreignId('school_section_id')->nullable()->constrained('school_sections')->cascadeOnDelete()->index();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['school_id', 'department_id', 'role_id', 'school_section_id'], 'department_role_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('department_role');
    }
};
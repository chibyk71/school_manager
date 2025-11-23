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
        Schema::create('staff_department_role', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignUuid('department_role_id')->constrained('department_role')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['staff_id', 'department_role_id'], 'staff_department_role_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_department_role');
    }
};

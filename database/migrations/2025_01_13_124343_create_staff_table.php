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
        Schema::create('staff', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->index()->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('school_id')->index()->constrained('schools')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'school_id'], 'staff_user_school_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};

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
        Schema::create('guardians', function (Blueprint $table) {
            $table->Uuid('id')->primary();
            $table->foreignUuid('user_id')->index()->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('school_id')->index()->constrained('schools')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Ensure unique user-school combination
            $table->unique(['user_id', 'school_id'], 'guardians_user_school_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guardians');
    }
};

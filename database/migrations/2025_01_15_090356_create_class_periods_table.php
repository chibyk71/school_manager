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
        Schema::create('class_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->unsignedMediumInteger('order')->index();
            $table->boolean('is_break')->default(false);
            $table->decimal('duration', 5, 2); // e.g., 1.50 hours
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['school_id', 'order'], 'class_periods_unique'); // Ensure unique order per school
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_periods');
    }
};

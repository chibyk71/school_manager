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
            $table->id();
            $table->string('school_id')->nullable()->index();
            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->unsignedMediumInteger('order');
            $table->boolean('is-break')->default(false);
            $table->decimal('duration');
            $table->timestamps();
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

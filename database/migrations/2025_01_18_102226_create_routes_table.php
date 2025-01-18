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
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->string('status');
            $table->string('starting_piont');
            $table->string('ending_point');
            $table->string('distance');
            $table->string('duration');
            $table->foreignId('fee_id')->constrained('fees')->cascadeOnDelete();
            $table->string('school_id')->index();
            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routes');
    }
};

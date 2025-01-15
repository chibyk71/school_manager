<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Constraint\Constraint;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('time_tables', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->boolean('status')->default('true');
            $table->dateTime('effective_date');
            $table->json('options')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['term_id', 'school_section_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_tables');
    }
};

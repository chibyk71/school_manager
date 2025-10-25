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
        Schema::create('time_tables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete()->index();
            $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete()->index();
            $table->string('title')->index();
            $table->dateTime('effective_date');
            $table->enum('status', ['active', 'draft', 'inactive'])->default('draft');
            $table->json('options')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['school_id', 'term_id', 'title'], 'time_tables_unique');
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
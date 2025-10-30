<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id')->nullable();
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->foreignUuid('term_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->integer('weight');
            $table->integer('max_score');
            $table->date('date_effective')->index();
            $table->date('date_due')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->text('instruction')->nullable();
            $table->timestamps();

            // Composite index for common queries
            $table->index(['school_id', 'term_id', 'date_effective']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};

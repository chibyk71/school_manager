<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->nullable()->constrained('schools')->cascadeOnDelete();
            $table->foreignUuid('event_type_id')->constrained('event_types')->cascadeOnDelete();
            $table->foreignUuid('term_id')->constrained('terms')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->time('start_time')->nullable();
            $table->date('end_date')->nullable();
            $table->time('end_time')->nullable();
            $table->string('venue')->nullable();
            $table->json('options')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('school_id');
            $table->index('event_type_id');
            $table->index('term_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};

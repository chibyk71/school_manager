<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('label');                // UI label
            $table->string('name');                 // machine name (e.g., currency)
            $table->string('applies_to')->nullable(); // Model class (e.g., App\Models\School)
            $table->mediumText('description')->nullable();
            $table->string('color')->nullable();    // optional color preview
            $table->json('options');                // possible values (e.g., ['USD', 'EUR'])
            $table->foreignUuid('school_id')->nullable()->constrained('schools')->cascadeOnDelete();    // system = null, school = School

            $table->timestamps();

            // Composite indexes – critical for performance and uniqueness
            $table->unique(['name', 'applies_to', 'school_id']);
            $table->index(['applies_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configs');
    }
};
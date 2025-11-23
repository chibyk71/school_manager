<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// database/migrations/2025_11_19_100300_create_admission_exam_results_table.php

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_exam_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('application_id')
                  ->constrained('admission_applications')
                  ->cascadeOnDelete();
            $table->json('scores'); // { "English": 85, "Mathematics": 92, ... }
            $table->integer('total_score');
            $table->integer('rank')->nullable();
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamps();

            $table->unique('application_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_exam_results');
    }
};

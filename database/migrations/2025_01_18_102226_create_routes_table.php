<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Finance\Fee;
use App\Models\School;
use App\Models\Transport\Route;

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
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->string('starting_point');
            $table->string('ending_point');
            $table->string('distance');
            $table->string('duration');
            $table->foreignId('fee_id')->nullable()->constrained('fees')->cascadeOnDelete();
            $table->foreignUuid('school_id')->index()->constrained('schools')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['school_id', 'status']);
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

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Employee\Staff;
use App\Models\School;
use App\Models\Transport\Vehicle\DriverAssignment;
use App\Models\Transport\Vehicle\Vehicle;
use App\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('driver_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignUuid('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->timestamp('effective_date');
            $table->enum('role', ['driver', 'incharge'])->default('driver');
            $table->timestamp('unassigned_at')->nullable();
            $table->json('options')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['vehicle_id', 'staff_id']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_assignments');
    }
};

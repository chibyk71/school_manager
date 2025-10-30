<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\School;
use App\Models\Transport\Route;
use App\Models\Transport\RouteVehicle;
use App\Models\Transport\Vehicle;
use App\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('route_vehicle', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('route_id')->constrained('routes')->cascadeOnDelete();
            $table->foreignUuid('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['route_id', 'vehicle_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('route_vehicle');
    }
};

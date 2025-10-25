<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Hostel\Hostel;
use App\Models\Hostel\HostelRoom;
use App\Models\School;
use App\Models\Staff;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hostel_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hostel_id')->constrained('hostels')->cascadeOnDelete();
            $table->string('room_number');
            $table->integer('capacity');
            $table->text('description')->nullable();
            $table->json('options')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('hostel_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hostel_rooms');
    }
};

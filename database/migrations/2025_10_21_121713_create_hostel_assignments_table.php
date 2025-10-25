<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Hostel\Hostel;
use App\Models\Hostel\HostelRoom;
use App\Models\Hostel\HostelAssignment;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hostel_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hostel_room_id')->constrained('hostel_rooms')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->enum('status', ['checked-in', 'checked-out'])->default('checked-in');
            $table->date('check_in_date');
            $table->date('check_out_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['hostel_room_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hostel_assignments');
    }
};

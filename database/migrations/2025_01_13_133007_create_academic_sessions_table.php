<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the academic_sessions table with fields for multi-tenant academic session management.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('academic_sessions', function (Blueprint $table) {
            $table->Uuid('id')->primary()->comment('Primary key as UUID for global uniqueness');
            $table->string('name')->nullable()->comment('Name of the academic session (e.g., 2024/2025)');
            $table->date('start_date')->comment('Start date of the academic session');
            $table->date('end_date')->comment('End date of the academic session');
            $table->boolean('is_current')->default(false)->comment('Indicates if this is the current active session');
            $table->foreignUuid('school_id')->constrained('schools')->onDelete('cascade')->comment('Reference to the school');
            $table->timestamps();
            $table->softDeletes()->comment('Soft delete timestamp for retaining historical data');
            $table->unique(['name', 'school_id'], 'academic_sessions_name_school_unique')->comment('Unique constraint for session name per school');
            $table->index('is_current', 'academic_sessions_is_current_index')->comment('Index for querying current sessions');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_sessions');
    }
};

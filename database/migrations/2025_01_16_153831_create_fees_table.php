<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade')->comment('The school associated with this fee');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('cascade')->comment('The branch associated with this fee, if applicable');
            $table->foreignId('fee_type_id')->constrained('fee_types')->onDelete('cascade')->comment('The type of fee (e.g., tuition, sports)');
            $table->foreignId('term_id')->constrained('terms')->onDelete('cascade')->comment('The academic term for this fee');
            $table->foreignId('recorded_by')->constrained('users')->onDelete('restrict')->comment('The user who recorded the fee');
            $table->string('description')->nullable()->comment('Optional description of the fee');
            $table->decimal('amount', 15, 2)->comment('The fee amount');
            $table->date('due_date')->comment('The due date for the fee payment');
            $table->timestamps();
            $table->softDeletes()->comment('Soft delete column for recoverable deletion');
            $table->index(['fee_type_id', 'term_id', 'school_id'])->comment('Index for common queries');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fees');
    }
};
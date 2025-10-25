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
        Schema::create('fee_concessions', function (Blueprint $table) {
            $table->id()->comment('Primary key for the fee concession');
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade')->comment('The school associated with this fee concession');
            $table->foreignId('fee_type_id')->constrained('fee_types')->onDelete('cascade')->comment('The fee type associated with this concession');
            $table->string('name')->comment('Name of the fee concession (e.g., Merit Scholarship)');
            $table->string('description')->nullable()->comment('Optional description of the fee concession');
            $table->enum('type', ['amount', 'percent'])->comment('Type of concession: fixed amount or percentage');
            $table->decimal('amount', 15, 2)->comment('Amount or percentage of the concession');
            $table->date('start_date')->nullable()->comment('Start date of the concession validity');
            $table->date('end_date')->nullable()->comment('End date of the concession validity');
            $table->timestamps();
            $table->softDeletes()->comment('Soft delete column for recoverable deletion');
            $table->index(['school_id', 'fee_type_id'])->comment('Index for efficient querying by school and fee type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_concessions');
    }
};
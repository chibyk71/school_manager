<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete()->index();
            $table->foreignUuid('staff_id')->constrained('staff')->cascadeOnDelete()->index();
            $table->string('name');
            $table->enum('type', ['bonus', 'allowance', 'overtime', 'deduction'])->nullable();
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->date('effective_date');
            $table->enum('recurrence', ['one-time', 'daily', 'weekly', 'monthly'])->nullable();
            $table->date('recurrence_end_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['school_id', 'staff_id', 'name', 'type', 'effective_date'], 'salary_addons_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_addons');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
// database/migrations/2025_11_19_100200_create_admission_payments_table.php

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('payable'); // admission_applications or future invoice
            $table->foreignUuid('school_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->string('gateway');
            $table->decimal('amount', 12, 2);
            $table->enum('type', ['application_fee', 'acceptance_fee']);
            $table->enum('status', ['pending', 'successful', 'failed'])->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'type', 'status']);
            $table->index('reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_payments');
    }
};

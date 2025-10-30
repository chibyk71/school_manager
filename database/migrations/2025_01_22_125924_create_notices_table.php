<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Communication\Notice;
use App\Models\School;
use App\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('body');
            $table->foreignUuid('school_id')->nullable()->constrained('schools')->cascadeOnDelete();
            $table->foreignUuid('sender_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_public')->default(false);
            $table->date('effective_date');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['school_id', 'effective_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notices');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Communication\Feedback;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Str;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->uuidMorphs('feedbackable');
            $table->text('message');
            $table->string('subject');
            $table->enum('status', ['pending', 'reviewed', 'resolved'])->default('pending');
            $table->foreignUuid('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index('school_id');
            $table->index(['feedbackable_type', 'feedbackable_id']);
            $table->index('handled_by');
        });

        // Seed sample feedback
        if (app()->environment(['local', 'testing'])) {
            $school = School::factory()->create();
            $user = User::factory()->create();
            $feedback = Feedback::create([
                'id' => (string) Str::uuid(),
                'school_id' => $school->id,
                'feedbackable_id' => $user->id,
                'feedbackable_type' => User::class,
                'subject' => 'Sample Feedback',
                'message' => 'This is a sample feedback message.',
                'status' => 'pending',
                'handled_by' => null,
            ]);
            $feedback->addConfig('category', 'Suggestion');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};

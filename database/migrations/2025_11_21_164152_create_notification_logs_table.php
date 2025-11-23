<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained()->cascadeOnDelete();
            $table->morphs('notifiable');           // Parent, Student, User, etc.
            $table->morphs('notification');         // The actual Notification class (e.g. FeeOverdueReminder)
            $table->string('channel');               // 'sms', 'mail', 'whatsapp', 'push'
            $table->string('provider')->nullable();  // 'multitexter', 'twilio', 'mailgun', 'ses'
            $table->string('recipient', 255);        // phone or email
            $table->text('message');                 // SMS body or Email subject + preview
            $table->string('sender', 255)->nullable();
            $table->boolean('success')->default(false);
            $table->text('error')->nullable();
            $table->unsignedInteger('segments')->default(1); // SMS only
            $table->decimal('cost', 10, 4)->default(0);      // NGN, for billing
            $table->json('metadata')->nullable();    // delivery reports, message_id, etc.
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            // Indexes for fast filtering
            $table->index(['school_id', 'created_at']);
            $table->index('channel');
            $table->index('success');
            $table->index('provider');
            $table->index('notifiable_type', 'notifiable_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notification preferences live in settings key academic.promotion_notifications.
 * Remove the unused dedicated table created by the original promotion migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('promotion_notification_preferences');
    }

    public function down(): void
    {
        Schema::create('promotion_notification_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('event', 60);
            $table->boolean('notify_database')->default(true);
            $table->boolean('notify_mail')->default(true);
            $table->boolean('notify_sms')->default(false);
            $table->json('recipient_permissions')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'event'], 'promo_notif_prefs_school_event_unique');
        });
    }
};

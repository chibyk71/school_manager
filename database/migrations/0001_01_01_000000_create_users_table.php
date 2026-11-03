<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Authentication Account (User) schema — Phase 1 Person & Authentication foundation.
     * Username is the primary mandatory unique identifier.
     * Password is nullable for Provisioned accounts.
     * Explicit lifecycle: provisioned | active (replaces is_active).
     * force_password_change is independent of lifecycle.
     * Person primary email lives only on profiles.email; users store only
     * auth_email_verified_at + auth_email_enabled state.
     * Authentication phone is optional and independent of profiles.phone.
     * Laragear TwoFactor uses its own table.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('username')->unique();
            $table->string('password')->nullable();
            $table->string('lifecycle')->default('provisioned');
            $table->boolean('force_password_change')->default(false);
            $table->timestamp('auth_email_verified_at')->nullable();
            $table->boolean('auth_email_enabled')->default(false);
            $table->string('auth_phone')->nullable();
            $table->timestamp('auth_phone_verified_at')->nullable();
            $table->boolean('auth_phone_enabled')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index('lifecycle');
            $table->index('auth_phone');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};

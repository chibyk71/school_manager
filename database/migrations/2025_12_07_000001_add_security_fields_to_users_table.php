<?php
// database/migrations/2025_01_01_000001_add_security_fields_to_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
            $table->string('last_login_ip')->nullable()->after('last_login_at');
            $table->unsignedSmallInteger('login_attempts')->default(0)->after('last_login_ip');

            // Laravel Fortify / 2FA columns
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'last_login_at',
                'last_login_ip',
                'login_attempts',
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
            ]);
        });
    }
};

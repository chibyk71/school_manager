<?php

/**
 * Phase 3 — Registration date/window on admissions.
 *
 * Distinct from offer date and acceptance deadline.
 * registration_starts_at / registration_ends_at form an optional window;
 * registration_date is a single expected registration day when no window is used.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admissions')) {
            return;
        }

        Schema::table('admissions', function (Blueprint $table) {
            if (! Schema::hasColumn('admissions', 'registration_date')) {
                $table->date('registration_date')->nullable()->after('cancelled_at');
            }
            if (! Schema::hasColumn('admissions', 'registration_starts_at')) {
                $table->timestamp('registration_starts_at')->nullable()->after('registration_date');
            }
            if (! Schema::hasColumn('admissions', 'registration_ends_at')) {
                $table->timestamp('registration_ends_at')->nullable()->after('registration_starts_at');
            }
            if (! Schema::hasColumn('admissions', 'reminder_sent_at')) {
                $table->timestamp('reminder_sent_at')->nullable()->after('registration_ends_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('admissions')) {
            return;
        }

        Schema::table('admissions', function (Blueprint $table) {
            foreach (['registration_date', 'registration_starts_at', 'registration_ends_at', 'reminder_sent_at'] as $col) {
                if (Schema::hasColumn('admissions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

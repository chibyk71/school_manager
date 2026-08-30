<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2: application fee payment state on student_applications.
 *
 * Tracks whether the configured application fee is satisfied without creating
 * a parallel payment ledger. Finance remains the source of truth for money movement;
 * this column records the integration outcome (paid / waived / unpaid / not_required).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('student_applications', 'fee_payment_status')) {
                $table->string('fee_payment_status', 30)
                    ->default('not_required')
                    ->after('custom_data');
            }
            if (! Schema::hasColumn('student_applications', 'fee_payment_reference')) {
                $table->string('fee_payment_reference', 191)->nullable()->after('fee_payment_status');
            }
            if (! Schema::hasColumn('student_applications', 'fee_paid_at')) {
                $table->timestamp('fee_paid_at')->nullable()->after('fee_payment_reference');
            }
        });
    }

    public function down(): void
    {
        Schema::table('student_applications', function (Blueprint $table) {
            $cols = array_filter([
                Schema::hasColumn('student_applications', 'fee_payment_status') ? 'fee_payment_status' : null,
                Schema::hasColumn('student_applications', 'fee_payment_reference') ? 'fee_payment_reference' : null,
                Schema::hasColumn('student_applications', 'fee_paid_at') ? 'fee_paid_at' : null,
            ]);
            if ($cols) {
                $table->dropColumn($cols);
            }
        });
    }
};

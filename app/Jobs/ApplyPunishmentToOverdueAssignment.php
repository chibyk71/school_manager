<?php

namespace App\Jobs;

use App\Models\Finance\FeeAssignment;
use App\Models\Finance\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApplyPunishmentToOverdueAssignment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $queue = 'finance';
    public $tries = 3;

    public function __construct(protected FeeAssignment $assignment) {}

    public function handle(): void
    {
        $assignment = $this->assignment->fresh(['fee.term', 'user']);

        if ($assignment->status === 'paid') {
            return; // Already paid
        }

        $settings = getMergedSettings('fees', $assignment->fee->school);

        if (
            !($settings['late_payment_punishment']['enabled'] ?? false) ||
            $assignment->balance <= 0
        ) {
            return;
        }

        $punishmentConfig = $settings['late_payment_punishment'];
        $dueDate = $assignment->due_date;
        $graceDays = $punishmentConfig['grace_period_days'] ?? 0;
        $effectiveDueDate = $dueDate->copy()->addDays($graceDays);

        if (now() < $effectiveDueDate) {
            return; // Still in grace period
        }

        $daysOverdue = now()->startOfDay()->diffInDays($effectiveDueDate, false);

        if ($daysOverdue <= 0) {
            return;
        }

        // Prevent duplicate daily punishment
        $lastPunishment = $assignment->transactions()
            ->where('category', 'late_fee')
            ->whereDate('transaction_date', now()->toDateString())
            ->exists();

        if ($lastPunishment && $punishmentConfig['apply_per'] === 'day') {
            return;
        }

        if ($punishmentConfig['apply_per'] === 'once' && $assignment->transactions()->where('category', 'late_fee')->exists()) {
            return;
        }

        $base = $assignment->balance;
        $punishmentAmount = $punishmentConfig['type'] === 'percentage'
            ? $base * ($punishmentConfig['amount'] / 100)
            : $punishmentConfig['amount'];

        // Apply per-day multiplier if needed
        if ($punishmentConfig['apply_per'] === 'day') {
            $punishmentAmount *= $daysOverdue;
        }

        if ($punishmentAmount <= 0) {
            return;
        }

        DB::transaction(function () use ($assignment, $punishmentAmount, $punishmentConfig, $daysOverdue) {
            // 1. Increase amount_due
            $assignment->amount_due += $punishmentAmount;
            $assignment->status = 'overdue';
            $assignment->saveQuietly();

            // 2. Create transaction (expense for student, income for school)
            $assignment->createTransaction([
                'transaction_type' => 'income',
                'category' => 'late_fee',
                'amount' => $punishmentAmount,
                'transaction_date' => now(),
                'description' => "Late payment punishment ({$punishmentConfig['type']} " .
                    number_format($punishmentAmount, 2) . ") applied after {$daysOverdue} days",
                'reference' => 'LATE-' . $assignment->id . '-' . now()->format('Ymd'),
                'meta' => [
                    'reason' => 'late_payment_punishment',
                    'days_overdue' => $daysOverdue,
                    'original_balance' => $assignment->balance - $punishmentAmount,
                ],
            ]);

            // 3. Optional: Notify parent
            // Notification::send($assignment->user, new LateFeeAppliedNotification($assignment, $punishmentAmount));
        });

        Log::info("Late fee of ₦{$punishmentAmount} applied to student {$assignment->user->name} (ID: {$assignment->user_id})");
    }
}

<?php

namespace App\Listeners;

use App\Events\PaymentReceived;
use App\Models\Finance\FeeAssignment;
use App\Notifications\PaymentReceiptNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ProcessPaymentAndSendReceipt implements ShouldQueue
{
    use InteractsWithQueue;

    public $queue = 'finance';

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(PaymentReceived $event): void
    {
        $payment = $event->payment->load('user', 'fee', 'feeInstallmentDetail');

        DB::transaction(function () use ($payment) {
            // 1. Create Transaction ledger entry
            $payment->createTransaction([
                'reference' => $payment->payment_reference,
                'description' => "Payment for {$payment->fee?->feeType?->name} by {$payment->user?->name}",
                'meta' => [
                    'method' => $payment->payment_method,
                    'currency' => $payment->payment_currency,
                ],
            ]);

            // 2. Update FeeAssignment (if linked)
            if ($payment->fee_id && $payment->user_id) {
                $assignment = FeeAssignment::where([
                    'fee_id' => $payment->fee_id,
                    'user_id' => $payment->user_id,
                ])->first();

                if ($assignment) {
                    $assignment->amount_paid += $payment->payment_amount;
                    $assignment->status = $assignment->balance <= 0 ? 'paid' : ($assignment->amount_paid > 0 ? 'partial' : 'pending');
                    $assignment->save();
                }
            }

            // 3. Send Receipt (Email + SMS)
            Notification::send($payment->user, new PaymentReceiptNotification($payment));
        });
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Finance\FeeInstallmentDetail;
use App\Models\User;
use App\Notifications\OverduePaymentNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class NotifyOverduePayments extends Command
{
    protected $signature = 'payments:notify-overdue';
    protected $description = 'Notify finance managers and students about overdue fee installment details';

    public function handle()
    {
        $overdueDetails = FeeInstallmentDetail::where('status', 'overdue')
            ->where('due_date', '<', now())
            ->with(['school', 'user'])
            ->get();

        foreach ($overdueDetails as $detail) {
            $recipients = User::where('school_id', $detail->school_id)
                ->whereHas('roles', fn($query) => $query->where('name', 'finance_manager'))
                ->get()
                ->merge(User::where('id', $detail->user_id)->get());

            Notification::send($recipients, new OverduePaymentNotification($detail));
        }

        $this->info('Overdue payment notifications sent successfully.');
    }
}
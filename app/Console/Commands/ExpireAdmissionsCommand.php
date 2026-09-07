<?php

namespace App\Console\Commands;

use App\Services\Student\AdmissionService;
use Illuminate\Console\Command;

/**
 * Expire outstanding admission offers past their acceptance deadline.
 * Also sends deadline and registration-window reminders.
 */
class ExpireAdmissionsCommand extends Command
{
    protected $signature = 'admissions:process-lifecycle
                            {--reminders : Also send deadline reminders}
                            {--reminder-hours=48 : Hours before acceptance deadline to remind}
                            {--registration-hours=72 : Hours before registration window end to remind}';

    protected $description = 'Expire past-deadline admission offers and optionally send reminders';

    public function handle(AdmissionService $admissionService): int
    {
        $expired = $admissionService->processExpiries();
        $this->info("Expired {$expired} admission offer(s).");

        if ($this->option('reminders')) {
            $hours = (int) $this->option('reminder-hours');
            $reminded = $admissionService->processDeadlineReminders($hours);
            $this->info("Sent {$reminded} acceptance deadline reminder(s).");

            $regHours = (int) $this->option('registration-hours');
            $regReminded = $admissionService->processRegistrationWindowReminders($regHours);
            $this->info("Sent {$regReminded} registration-window reminder(s).");
        }

        return self::SUCCESS;
    }
}

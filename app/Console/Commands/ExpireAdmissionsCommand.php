<?php

namespace App\Console\Commands;

use App\Services\Student\AdmissionService;
use Illuminate\Console\Command;

/**
 * Expire outstanding admission offers past their acceptance deadline.
 * Also sends deadline reminders for offers approaching the deadline.
 */
class ExpireAdmissionsCommand extends Command
{
    protected $signature = 'admissions:process-lifecycle
                            {--reminders : Also send deadline reminders}
                            {--reminder-hours=48 : Hours before deadline to remind}';

    protected $description = 'Expire past-deadline admission offers and optionally send deadline reminders';

    public function handle(AdmissionService $admissionService): int
    {
        $expired = $admissionService->processExpiries();
        $this->info("Expired {$expired} admission offer(s).");

        if ($this->option('reminders')) {
            $hours = (int) $this->option('reminder-hours');
            $reminded = $admissionService->processDeadlineReminders($hours);
            $this->info("Sent {$reminded} deadline reminder(s).");
        }

        return self::SUCCESS;
    }
}

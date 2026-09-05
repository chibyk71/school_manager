<?php

namespace App\Console\Commands;

use App\Services\Student\EnrollmentService;
use Illuminate\Console\Command;

/**
 * Safe-to-rerun enrollment operational reminders.
 * Uses EnrollmentService so domain rules stay authoritative.
 */
class ProcessEnrollmentRemindersCommand extends Command
{
    protected $signature = 'enrollments:process-reminders
                            {--incomplete-days=3 : Days since last update before incomplete reminder}
                            {--requirements : Also remind about outstanding requirements}';

    protected $description = 'Send incomplete-enrollment and outstanding-requirement reminders (idempotent)';

    public function handle(EnrollmentService $enrollmentService): int
    {
        $days = max(1, (int) $this->option('incomplete-days'));
        $incomplete = $enrollmentService->processIncompleteReminders($days);
        $this->info("Sent {$incomplete} incomplete-enrollment reminder(s).");

        if ($this->option('requirements')) {
            $reqs = $enrollmentService->processOutstandingRequirementReminders();
            $this->info("Sent {$reqs} outstanding-requirement reminder(s).");
        }

        return self::SUCCESS;
    }
}

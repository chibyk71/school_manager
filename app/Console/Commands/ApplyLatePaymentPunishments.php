<?php

namespace App\Console\Commands;

use App\Jobs\ApplyPunishmentToOverdueAssignment;
use App\Models\Finance\FeeAssignment;
use Illuminate\Console\Command;

class ApplyLatePaymentPunishments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finance:punish-overdue {--dry-run : Show what would be punished without applying}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Apply late payment punishment to overdue fee assignments';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting late payment punishment scan...');

        $query = FeeAssignment::query()
            ->where('status', '!=', 'paid')
            ->whereHas('fee', function ($q) {
                $q->where('school_id', function ($sq) {
                    $sq->select('school_id')->from('fee_assignments')->limit(1);
                });
            })
            ->whereColumn('amount_paid', '<', 'amount_due');

        $total = $query->count();

        if ($total === 0) {
            $this->info('No overdue assignments found.');
            return self::SUCCESS;
        }

        $this->info("Found {$total} overdue assignments.");

        if ($this->option('dry-run')) {
            $this->warn('Dry run mode — no punishments applied.');
            return self::SUCCESS;
        }

        // Chunk to avoid memory issues
        $query->chunkById(200, function ($assignments) {
            foreach ($assignments as $assignment) {
                ApplyPunishmentToOverdueAssignment::dispatchSync($assignment);
            }
        });

        $this->info('Punishment job completed.');

        return self::SUCCESS;
    }
}

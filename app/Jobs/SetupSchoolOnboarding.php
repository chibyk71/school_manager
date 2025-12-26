<?php

namespace App\Jobs;

use App\Models\School;
use App\Models\SchoolSection;
use App\Models\Academic\AcademicSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SetupSchoolOnboarding Job
 *
 * Purpose & Context:
 * ------------------
 * This queued job performs the heavy-lifting onboarding setup for a newly created school
 * after the initial HTTP request has completed successfully.
 *
 * Since default school settings are now inherited from tenant-level defaults
 * (and can be overridden later by admins), this job focuses solely on creating
 * essential structural data specific to Nigerian secondary schools:
 *
 * - Default school sections (e.g., Nursery, Primary, JSS, SSS)
 * - Default class levels (e.g., JSS1–3, SSS1–3, Nursery 1–3, Primary 1–6)
 * - Current academic session (based on Nigerian school calendar: September–July)
 *
 * Key Design Decisions:
 * ---------------------
 * - Implements ShouldQueue: Runs asynchronously to keep onboarding response fast
 * - Idempotent: Safe to rerun — checks for existence before creating records
 * - Transactional: Uses DB transaction to ensure data consistency
 * - Nigerian-context aware: Hardcoded defaults reflect common school structures
 * - Extensible: Easy to add more defaults (subjects, fee categories, terms, etc.)
 *
 * Why Queued?
 * -----------
 * - Creating multiple sections + class levels involves several DB writes
 * - Keeps public onboarding flow snappy (<2s response)
 * - Failure tolerant: Job can be retried without affecting school creation
 *
 * Failure Handling:
 * -----------------
 * - Logs detailed errors with school context
 * - Idempotency prevents duplicate records on retry
 * - Does not rollback school creation — school remains usable even if job fails
 *
 * Dispatch:
 * ---------
 * Dispatched from SchoolCreated event listener (or directly from controller/service)
 *
 * Example Defaults Created:
 * ------------------------
 * Sections: Nursery, Primary, Junior Secondary, Senior Secondary
 * Class Levels:
 *   - Nursery: Nursery 1, Nursery 2, Nursery 3
 *   - Primary: Primary 1–6
 *   - JSS: JSS1–3
 *   - SSS: SSS1–3
 * Current Session: e.g., "2025/2026" starting September 2025
 */
class SetupSchoolOnboarding implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The newly created school instance.
     *
     * @var School
     */
    public $school;

    /**
     * Create a new job instance.
     *
     * @param  School  $school
     * @return void
     */
    public function __construct(School $school)
    {
        $this->school = $school;
    }

    /**
     * Execute the job.
     *
     * Creates default sections, class levels, and current academic session.
     *
     * @return void
     */
    public function handle(): void
    {
        try {
            DB::transaction(function () {
                $this->createDefaultSections();
                $this->createDefaultClassLevels();
                $this->createCurrentAcademicSession();
            });

            Log::info('School onboarding setup completed successfully', [
                'school_id' => $this->school->id,
                'school_name' => $this->school->name,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to complete school onboarding setup', [
                'school_id' => $this->school->id,
                'school_name' => $this->school->name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Optional: notify admins or re-throw to trigger retry
            // throw $e;
        }
    }

    /**
     * Create default school sections if they don't exist.
     */
    protected function createDefaultSections(): void
    {
        $sections = [
            ['name' => 'Nursery', 'short_name' => 'NUR', 'order' => 1],
            ['name' => 'Primary', 'short_name' => 'PRI', 'order' => 2],
            ['name' => 'Junior Secondary', 'short_name' => 'JSS', 'order' => 3],
            ['name' => 'Senior Secondary', 'short_name' => 'SSS', 'order' => 4],
        ];

        foreach ($sections as $sectionData) {
            SchoolSection::firstOrCreate(
                [
                    'school_id' => $this->school->id,
                    'name' => $sectionData['name'],
                ],
                $sectionData
            );
        }
    }

    /**
     * Create default class levels per section.
     */
    protected function createDefaultClassLevels(): void
    {
        $levels = [
            'Nursery' => ['Nursery 1', 'Nursery 2', 'Nursery 3'],
            'Primary' => ['Primary 1', 'Primary 2', 'Primary 3', 'Primary 4', 'Primary 5', 'Primary 6'],
            'Junior Secondary' => ['JSS1', 'JSS2', 'JSS3'],
            'Senior Secondary' => ['SSS1', 'SSS2', 'SSS3'],
        ];

        foreach ($levels as $sectionName => $classNames) {
            $section = $this->school->sections()->where('name', $sectionName)->first();

            if (!$section) {
                continue;
            }

            foreach ($classNames as $index => $name) {
                \App\Models\Academic\ClassLevel::firstOrCreate(
                    [
                        'school_section_id' => $section->id,
                        'name' => $name,
                    ],
                    [
                        'short_name' => $name,
                        'order' => $index + 1,
                    ]
                );
            }
        }
    }

    /**
     * Create the current academic session based on Nigerian school calendar.
     *
     * Nigerian sessions typically run September–July.
     * Example: Current session in December 2025 → "2025/2026"
     */
    protected function createCurrentAcademicSession(): void
    {
        $currentYear = now()->year;
        $nextYear = $currentYear + 1;

        // If current month is September or later, session is current/next
        // Otherwise, it's previous/current
        $startYear = now()->month >= 9 ? $currentYear : $currentYear - 1;
        $endYear = $startYear + 1;

        $sessionName = "{$startYear}/{$endYear}";

        AcademicSession::firstOrCreate(
            [
                'school_id' => $this->school->id,
                'name' => $sessionName,
            ],
            [
                'start_date' => "{$startYear}-09-01",
                'end_date' => "{$endYear}-07-31",
                'is_current' => true,
            ]
        );

        // Ensure only one current session
        $this->school->academicSessions()
            ->where('id', '!=', AcademicSession::where('name', $sessionName)->first()->id)
            ->update(['is_current' => false]);
    }
}

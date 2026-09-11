<?php

namespace App\Jobs;

use App\Models\School;
use App\Models\SchoolSection;
use App\Models\Academic\AcademicSession;
use App\States\Academic\AcademicSession\Active as SessionActive;
use App\States\Academic\AcademicSession\Closed as SessionClosed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SetupSchoolOnboarding implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $school;

    public function __construct(School $school)
    {
        $this->school = $school;
    }

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
        }
    }

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
     * Onboarding creates the operating session as ACTIVE.
     */
    protected function createCurrentAcademicSession(): void
    {
        $currentYear = now()->year;

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
                'state' => SessionActive::$name,
                'activated_at' => now(),
            ]
        );

        $created = AcademicSession::where('school_id', $this->school->id)->where('name', $sessionName)->first();
        if ($created) {
            $this->school->academicSessions()
                ->where('id', '!=', $created->id)
                ->where('state', SessionActive::$name)
                ->update(['state' => SessionClosed::$name]);
        }
    }
}

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
            $this->school->refresh();
            $data = $this->school->data ?? [];
            if (! empty($data['operational_onboarding_completed_at'])) {
                Log::info('School onboarding skipped — already completed', [
                    'school_id' => $this->school->id,
                ]);

                return;
            }

            DB::transaction(function () {
                $this->createDefaultSections();
                $this->createDefaultClassLevels();
                $this->createCurrentAcademicSession();

                $school = School::query()->whereKey($this->school->id)->lockForUpdate()->first();
                if ($school) {
                    $d = $school->data ?? [];
                    $d['operational_onboarding_completed_at'] = now()->toIso8601String();
                    // Claim is no longer needed once complete.
                    unset($d['operational_onboarding_dispatch_claimed_at']);
                    $school->data = $d;
                    $school->save();
                }
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

            // Clear the dispatch claim so a later activate/retry can re-dispatch.
            // Do not set completed_at. Re-throw so the queue marks the job failed.
            $this->clearDispatchClaim();

            throw $e;
        }
    }

    /**
     * Release in-flight claim after a failed attempt (completed_at stays unset).
     * Row-locked transaction so a concurrent successful worker's completed_at is never overwritten.
     */
    protected function clearDispatchClaim(): void
    {
        try {
            DB::transaction(function () {
                $school = School::query()->whereKey($this->school->id)->lockForUpdate()->first();
                if (! $school) {
                    return;
                }
                $data = $school->data ?? [];
                // Never clear claim (or touch data) if onboarding already completed.
                if (! empty($data['operational_onboarding_completed_at'])) {
                    return;
                }
                unset(
                    $data['operational_onboarding_dispatch_claimed_at'],
                    $data['operational_onboarding_dispatched_at']
                );
                $school->data = $data;
                $school->save();
            });
        } catch (\Throwable $clearError) {
            Log::error('Failed to clear onboarding dispatch claim after job failure', [
                'school_id' => $this->school->id,
                'error' => $clearError->getMessage(),
            ]);
        }
    }

    protected function createDefaultSections(): void
    {
        // Field names must match SchoolSection fillable / school_sections schema
        // (name slug, display_name, short_code, sort_order) — not legacy short_name/order.
        $sections = [
            ['name' => 'nursery', 'display_name' => 'Nursery', 'short_code' => 'NUR', 'sort_order' => 1],
            ['name' => 'primary', 'display_name' => 'Primary', 'short_code' => 'PRI', 'sort_order' => 2],
            ['name' => 'junior_secondary', 'display_name' => 'Junior Secondary School', 'short_code' => 'JSS', 'sort_order' => 3],
            ['name' => 'senior_secondary', 'display_name' => 'Senior Secondary School', 'short_code' => 'SSS', 'sort_order' => 4],
        ];

        foreach ($sections as $sectionData) {
            SchoolSection::firstOrCreate(
                [
                    'school_id' => $this->school->id,
                    'name' => $sectionData['name'],
                ],
                array_merge($sectionData, [
                    'source' => 'template',
                    'is_active' => true,
                ])
            );
        }
    }

    protected function createDefaultClassLevels(): void
    {
        $levels = [
            'nursery' => ['Nursery 1', 'Nursery 2', 'Nursery 3'],
            'primary' => ['Primary 1', 'Primary 2', 'Primary 3', 'Primary 4', 'Primary 5', 'Primary 6'],
            'junior_secondary' => ['JSS1', 'JSS2', 'JSS3'],
            'senior_secondary' => ['SSS1', 'SSS2', 'SSS3'],
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

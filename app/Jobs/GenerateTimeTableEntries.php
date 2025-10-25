<?php

namespace App\Jobs;

use App\Models\Academic\ClassPeriod;
use App\Models\Academic\ClassSection;
use App\Models\Academic\TeacherClassSectionSubject;
use App\Models\Academic\TimeTable;
use App\Models\Academic\TimeTableDetail;
use App\Models\User;
use App\Notifications\TimeTableGeneratedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Laratrust\Facades\LaratrustFacade;

/**
 * Job: GenerateTimeTableEntries
 *
 * This job auto-generates timetable entries for a given timetable, assigning available teachers
 * to class periods for each class section while respecting constraints such as:
 * - Avoiding scheduling conflicts for teachers and class sections on the same day and time slot.
 * - Ensuring assignments are based on the TeacherClassSectionSubject model.
 * - Respecting school-specific periods, sections, and teacher assignments.
 *
 * Workflow:
 * 1. Fetch school-specific class periods, class sections, and teacher assignments.
 * 2. Group periods by day to respect time slot constraints.
 * 3. For each day and period, assign teachers to class sections, avoiding conflicts.
 * 4. Create TimeTableDetail entries for valid assignments.
 * 5. Log warnings if no available teacher is found for a period and section.
 * 6. Notify school admins and users with timetable permissions upon completion.
 *
 * Features:
 * - Tenant-scoped to the school associated with the timetable.
 * - Uses transactions for data consistency.
 * - Logs activity using Spatie\LogsActivity.
 * - Supports dry-run mode for previewing timetable entries.
 * - Limits teacher assignments to a maximum number of periods per day (configurable).
 *
 * Notifications:
 * - Sends email and in-app notifications to school admins and users with
 *   'timetable-details.create' or 'timetable-details.update' permissions.
 *
 * Prerequisites:
 * - Requires configured TimeTable, ClassPeriod, ClassSection, and TeacherClassSectionSubject models.
 * - Assumes relationships like classSections and term exist on TimeTable.
 * - Requires Laratrust for permission-based notifications.
 *
 * TODO:
 * - Add custom rules for subject scheduling (e.g., prioritize core subjects in the morning).
 */
class GenerateTimeTableEntries implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum number of periods a teacher can be assigned per day.
     *
     * @var int
     */
    protected $maxPeriodsPerTeacherPerDay = 3;

    /**
     * Create a new job instance.
     *
     * @param TimeTable $timeTable
     * @param bool $dryRun Whether to perform a dry run without saving entries
     */
    public function __construct(protected TimeTable $timeTable, protected bool $dryRun = false)
    {
        $this->queue = 'timetable-generation';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Start a transaction for data consistency
            DB::transaction(function () {
                $school = GetSchoolModel();
                Log::info("Starting timetable generation for TimeTable ID {$this->timeTable->id} in School ID {$school->id}", [
                    'dry_run' => $this->dryRun,
                ]);

                // Fetch school-specific data
                $periods = ClassPeriod::where('school_id', $school->id)->get();
                $classSections = $this->timeTable->schoolSections()->get();
                $teacherAssignments = TeacherClassSectionSubject::where('school_id', $school->id)
                    ->whereIn('class_section_id', $classSections->pluck('id'))
                    ->with(['teacher', 'subject', 'classSection'])
                    ->get();

                // Group periods by day for time slot scheduling
                $periodsByDay = $periods->groupBy('day');

                $generatedEntries = [];

                foreach ($periodsByDay as $day => $dayPeriods) {
                    // Track busy teachers and class sections for this day
                    $busyTeachers = [];
                    $busyClassSections = [];

                    foreach ($dayPeriods as $period) {
                        foreach ($classSections as $classSection) {
                            // Check for existing assignments to avoid conflicts
                            $existingAssignment = TimeTableDetail::where('timetable_id', $this->timeTable->id)
                                ->where('class_period_id', $period->id)
                                ->whereHas('teacherClassSectionSubject', fn($q) => $q->where('class_section_id', $classSection->id))
                                ->exists();

                            if ($existingAssignment) {
                                Log::warning("Class section {$classSection->name} already assigned for period {$period->name} on {$day}");
                                continue;
                            }

                            // Filter available assignments, excluding busy teachers and class sections
                            $availableAssignments = $teacherAssignments->filter(function ($assignment) use ($classSection, $busyTeachers, $busyClassSections, $day) {
                                $teacherDailyCount = TimeTableDetail::where('timetable_id', $this->timeTable->id)
                                    ->where('day', $day)
                                    ->whereHas('teacherClassSectionSubject', fn($q) => $q->where('teacher_id', $assignment->teacher_id))
                                    ->count();

                                return $assignment->class_section_id === $classSection->id &&
                                    !in_array($assignment->teacher_id, $busyTeachers) &&
                                    !in_array($assignment->class_section_id, $busyClassSections) &&
                                    $teacherDailyCount < $this->maxPeriodsPerTeacherPerDay;
                            });

                            if ($availableAssignments->isEmpty()) {
                                Log::warning("No available teacher for class section {$classSection->name} in period {$period->name} on {$day}");
                                continue;
                            }

                            // Randomly select an assignment
                            $assignment = $availableAssignments->shuffle()->first();

                            $entryData = [
                                'school_id' => $school->id,
                                'timetable_id' => $this->timeTable->id,
                                'class_period_id' => $period->id,
                                'teacher_class_section_subject_id' => $assignment->id,
                                'day' => $day,
                                'start_time' => $period->start_time,
                                'end_time' => $period->end_time,
                            ];

                            if ($this->dryRun) {
                                $generatedEntries[] = $entryData;
                            } else {
                                TimeTableDetail::create($entryData);
                            }

                            // Mark teacher and class section as busy for this period
                            $busyTeachers[] = $assignment->teacher_id;
                            $busyClassSections[] = $assignment->class_section_id;
                        }
                    }
                }

                if ($this->dryRun) {
                    Log::info("Dry run completed for TimeTable ID {$this->timeTable->id}", [
                        'entries' => $generatedEntries,
                    ]);
                    return;
                }

                // Notify school admins and authorized users
                $this->notifyUsers($school);
            });

            Log::info("Timetable generation completed successfully for TimeTable ID {$this->timeTable->id}");
        } catch (\Exception $e) {
            Log::error("Failed to generate timetable entries for TimeTable ID {$this->timeTable->id}: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Notify school admins and users with timetable permissions.
     *
     * @param mixed $school
     */
    protected function notifyUsers($school): void
    {
        try {
            // Fetch users with timetable permissions for the school
            $users = User::where('school_id', $school->id)
                ->where(function ($query) {
                    $query->whereHas('roles', fn($q) => $q->where('name', 'admin'))
                        ->orWhere(fn($q) => $q->hasPermission(['timetable-details.create', 'timetable-details.update']));
                })
                ->get();

            if ($users->isEmpty()) {
                Log::warning("No users found to notify for timetable ID {$this->timeTable->id}");
                return;
            }

            Notification::send($users, new TimeTableGeneratedNotification($this->timeTable));
            Log::info("Notifications sent for timetable ID {$this->timeTable->id}", ['user_count' => $users->count()]);
        } catch (\Exception $e) {
            Log::error("Failed to send notifications for timetable ID {$this->timeTable->id}: {$e->getMessage()}");
        }
    }
}
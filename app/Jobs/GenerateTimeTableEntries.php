<?php

namespace App\Jobs;

use App\Models\TeacherSubjectClassSection;
use App\Models\Tenant\ClassSection;
use App\Models\Tenant\Period;
use App\Models\Tenant\TimeTable;
use App\Models\Tenant\TimeTableEntry;
use App\Models\User;
use App\Notifications\TimeTableGeneratedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

/**
 * Job: GenerateTimeTableEntries
 *
 * This job is responsible for auto-generating timetable entries for a given timetable.
 * It assigns available teachers to periods for each class section while respecting constraints such as:
 * - Avoiding scheduling conflicts where a teacher is already assigned to another period.
 * - Ensuring that each class section is assigned the correct subjects and teachers based on the 
 *   teacher-subject-class-section relationships in the database.
 *
 * The workflow involves:
 * 1. Fetching all periods defined for the school.
 * 2. Fetch all class sections defined for the school.
 * 3. Fetch Teachers and Subjects, through the TeacherSubjectClassSection model, for each class section.
 * 4. For each period Iterate through each class section, and tru to assign a subject and teacher, the get the curresponding, id from the TeacherSubjectClassSection Model.
 * 5. Check for teacher availability for the specific period to avoid scheduling conflicts.
 * 6. If teacher is not available retry for a different teacher
 * 7. Creating timetable entries by associating periods, class sections, subjects, and teachers.
 *
 * If no available teacher is found for a class section during a specific period, a warning is logged.
 * After generating the timetable entries, this job sends a notification to the admins of the school sections 
 * associated with the timetable, informing them that a draft timetable has been generated.
 *
 * This class is designed to be queued for asynchronous execution, ensuring that large-scale timetable 
 * generation does not block other processes.
 *
 * Notifications:
 * - Sends both email and in-app notifications to the school section admins to review and approve the generated timetable.
 *
 * Prerequisites:
 * - The related `TimeTable`, `Period`, `ClassSection`, and `TeacherSubjectClassSection` models should be properly configured.
 * - Eloquent relationships like `classSections`, `admins`, and `term` are assumed to exist and provide the necessary data.
 *
 * Potential Improvements:
 * TODO Add a dry-run feature to preview generated timetables.
 * TODO Incorporate custom rules for subject scheduling (e.g., specific subjects in the morning).
 * TODO Handle edge cases where teachers may have a maximum number of periods per day.
 */

class GenerateTimeTableEntries implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(protected TimeTable $timeTable)
    {
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $periods = Period::all();
        $classSections = ClassSection::all(); //
        $teacherAssignments = TeacherSubjectClassSection::whereIn('class_section_id', $classSections->pluck('id'))->get();

        // here we loop through the periods and create enteries for every class section
        foreach ($periods as $period) {
            // an array to store teachers that are already take for this period, so they cant be reassigned, maybe till next period
            $busyTeachers = [];
            // $busyTeachers = $this->getBusyTeachers($period);

            foreach ($classSections as $classSection) {
                // before we continue we check, that the teacher is not busy, else get another subject 

                // Filter available assignments for the current class section and period, excluding busy teachers.
                $availableAssignments = $teacherAssignments->filter(function ($assignment) use ($classSection, $busyTeachers) {
                    return $assignment->class_section_id === $classSection->id &&
                        !in_array($assignment->teacher_id, $busyTeachers);
                });

                if ($availableAssignments->isEmpty()) {
                    \Log::warning("No available teacher for class section {$classSection->id} in period {$period->id}");
                    continue;
                }

                // from the available assignments suffle and get a random item from the collection, and add that to the entery
                $assignment = $availableAssignments->shuffle()->random();

                TimeTableEntry::create([
                    'teacher_subject_class_id' => $assignment->id,
                    'time_table_id' => $this->timeTable->id,
                    'period_id' => $period->id,
                ]);

                $busyTeachers[] = $assignment->teacher_id;
            }
        }

        // Notify school section admins
        // TODO tis should reflect the type of data been returned byb laratrust
        // TODO and also this should include, users with permission to create and edit timetables
        $admins = User::whereHas('rolesTeams', function ($query) {
            $query->where('team_id', tenant()->schoolSection->id)
                ->where('role.name', 'admin');
        }); // Assumes relationship exists

        Notification::send($admins, new TimeTableGeneratedNotification($this->timeTable));
    }
}

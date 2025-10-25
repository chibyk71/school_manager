<?php

namespace App\Http\Controllers\Exam;

use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\ClassLevel;
use App\Models\Academic\Subject;
use App\Models\Exam\ExamSchedule;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

/**
 * Controller for managing exam schedules in a single-tenant school system.
 */
class ScheduleController extends Controller
{
    /**
     * Display a listing of exam schedules.
     *
     * Retrieves exam schedules for the active school and current academic session, along with related data.
     *
     * @return \Inertia\Response The Inertia response with schedules and related data.
     *
     * @throws \Exception If data retrieval fails or no active school is found.
     */
    public function index()
    {
        try {
            permitted('view-exam-schedules');

            $school = GetSchoolModel();
            if (!$school) {
                abort(403, 'No active school found.');
            }

            $currentSession = AcademicSession::where('school_id', $school->id)
                ->where('is_current', true)
                ->first();

            $schedules = ExamSchedule::where('school_id', $school->id)
                ->when($currentSession, function ($query) use ($currentSession) {
                    $query->where('academic_session_id', $currentSession->id);
                })
                ->with(['academicSession', 'classLevel', 'subject'])
                ->get()
                ->map(function ($schedule) {
                    return [
                        'id' => $schedule->id,
                        'academic_session' => $schedule->academicSession?->name,
                        'class_level' => $schedule->classLevel?->name,
                        'subject' => $schedule->subject?->name,
                        'exam_date' => $schedule->exam_date->format('Y-m-d'),
                        'start_time' => $schedule->start_time->format('H:i'),
                        'end_time' => $schedule->end_time->format('H:i'),
                        'venue' => $schedule->venue,
                    ];
                });

            $classLevels = ClassLevel::where('school_id', $school->id)
                ->pluck('name', 'id');
            $subjects = Subject::where('school_id', $school->id)
                ->pluck('name', 'id');

            return Inertia::render('Academic/Exam/Schedules', [
                'schedules' => $schedules,
                'classLevels' => $classLevels,
                'subjects' => $subjects,
                'currentSession' => $currentSession ? [
                    'id' => $currentSession->id,
                    'name' => $currentSession->name,
                ] : null,
            ], 'resources/js/Pages/Academic/Exam/Schedules.vue');
        } catch (\Exception $e) {
            Log::error('Failed to fetch exam schedules: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load exam schedules.');
        }
    }

    /**
     * Store a new exam schedule.
     *
     * Creates a new exam schedule for the active school with validated data.
     *
     * @param Request $request The incoming HTTP request.
     * @return \Illuminate\Http\RedirectResponse Redirects on success.
     *
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If schedule creation fails.
     */
    public function store(Request $request)
    {
        try {
            permitted('create-exam-schedules');

            $school = GetSchoolModel();
            if (!$school) {
                abort(403, 'No active school found.');
            }

            $validated = $request->validate([
                'academic_session_id' => 'required|exists:academic_sessions,id,school_id,' . $school->id,
                'class_level_id' => 'required|exists:class_levels,id,school_id,' . $school->id,
                'subject_id' => 'required|exists:subjects,id,school_id,' . $school->id,
                'exam_date' => 'required|date|after_or_equal:today',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'venue' => 'nullable|string|max:255',
            ]);

            ExamSchedule::create(array_merge($validated, ['school_id' => $school->id]));

            return redirect()
                ->route('exam.schedules.index')
                ->with('success', 'Exam schedule created successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to create exam schedule: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to create exam schedule: ' . $e->getMessage());
        }
    }

    /**
     * Update an existing exam schedule.
     *
     * Updates an exam schedule for the active school with validated data.
     *
     * @param Request $request The incoming HTTP request.
     * @param ExamSchedule $schedule The schedule to update.
     * @return \Illuminate\Http\RedirectResponse Redirects on success.
     *
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If schedule update fails.
     */
    public function update(Request $request, ExamSchedule $schedule)
    {
        try {
            permitted('edit-exam-schedules');

            $school = GetSchoolModel();
            if (!$school || $schedule->school_id !== $school->id) {
                abort(403, 'Unauthorized access to schedule.');
            }

            $validated = $request->validate([
                'academic_session_id' => 'required|exists:academic_sessions,id,school_id,' . $school->id,
                'class_level_id' => 'required|exists:class_levels,id,school_id,' . $school->id,
                'subject_id' => 'required|exists:subjects,id,school_id,' . $school->id,
                'exam_date' => 'required|date|after_or_equal:today',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'venue' => 'nullable|string|max:255',
            ]);

            $schedule->update($validated);

            return redirect()
                ->route('exam.schedules.index')
                ->with('success', 'Exam schedule updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to update exam schedule: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update exam schedule: ' . $e->getMessage());
        }
    }

    /**
     * Delete an exam schedule.
     *
     * Deletes an exam schedule for the active school.
     *
     * @param ExamSchedule $schedule The schedule to delete.
     * @return \Illuminate\Http\RedirectResponse Redirects on success.
     *
     * @throws \Exception If schedule deletion fails.
     */
    public function destroy(ExamSchedule $schedule)
    {
        try {
            permitted('delete-exam-schedules');

            $school = GetSchoolModel();
            if (!$school || $schedule->school_id !== $school->id) {
                abort(403, 'Unauthorized access to schedule.');
            }

            $schedule->delete();

            return redirect()
                ->route('exam.schedules.index')
                ->with('success', 'Exam schedule deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete exam schedule: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to delete exam schedule: ' . $e->getMessage());
        }
    }
}

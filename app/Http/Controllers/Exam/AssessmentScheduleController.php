<?php

namespace App\Http\Controllers\Exam;

use App\Http\Controllers\Controller;
use App\Models\Academic\ClassSection;
use App\Models\Academic\Subject;
use App\Models\Employee\Staff;
use App\Models\Exam\Assessment;
use App\Models\Exam\AssessmentSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

/**
 * Controller for managing assessment schedules in the school management system.
 */
class AssessmentScheduleController extends Controller
{
    /**
     * Display a listing of assessment schedules.
     *
     * @param Request $request The HTTP request instance.
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function index(Request $request)
    {
        try {
            permitted('view-assessment-schedules');

            $school = GetSchoolModel();
            if (!$school) {
                abort(403, 'No active school found.');
            }

            $query = AssessmentSchedule::query();
            if ($request->has('noFallback')) {
                $query->withoutFallback();
            }

            $schedules = $query->map(function ($schedule) {
                return [
                    'id' => $schedule->id,
                    'assessment' => $schedule->assessment?->name,
                    'subject' => $schedule->subject?->name,
                    'class_section' => $schedule->classSection?->name,
                    'invigilator' => $schedule->invigilator?->name,
                    'start_date' => $schedule->start_date->format('Y-m-d'),
                    'end_date' => $schedule->end_date->format('Y-m-d'),
                    'start_time' => $schedule->start_time->format('H:i'),
                    'end_time' => $schedule->end_time->format('H:i'),
                    'status' => $schedule->status,
                    'venue' => $schedule->venue,
                ];
            });

            $assessments = Assessment::where('school_id', $school->id)->pluck('name', 'id');
            $subjects = Subject::where('school_id', $school->id)->pluck('name', 'id');
            $classSections = ClassSection::where('school_id', $school->id)->pluck('name', 'id');
            $staff = Staff::where('school_id', $school->id)->pluck('name', 'id');

            return Inertia::render('Exam/AssessmentSchedules', [
                'schedules' => $schedules,
                'assessments' => $assessments,
                'subjects' => $subjects,
                'classSections' => $classSections,
                'staff' => $staff,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch assessment schedules: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load assessment schedules.');
        }
    }

    /**
     * Show the form for creating a new assessment schedule.
     *
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function create()
    {
        try {
            permitted('create-assessment-schedules');

            $school = GetSchoolModel();
            if (!$school) {
                abort(403, 'No active school found.');
            }

            $assessments = Assessment::where('school_id', $school->id)->pluck('name', 'id');
            $subjects = Subject::where('school_id', $school->id)->pluck('name', 'id');
            $classSections = ClassSection::where('school_id', $school->id)->pluck('name', 'id');
            $staff = Staff::where('school_id', $school->id)->pluck('name', 'id');

            return Inertia::render('Exam/AssessmentSchedules/Create', [
                'assessments' => $assessments,
                'subjects' => $subjects,
                'classSections' => $classSections,
                'staff' => $staff,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load create assessment schedule form: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load create form.');
        }
    }

    /**
     * Store a newly created assessment schedule in storage.
     *
     * @param Request $request The HTTP request instance.
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        try {
            permitted('create-assessment-schedules');

            $school = GetSchoolModel();
            if (!$school) {
                abort(403, 'No active school found.');
            }

            $validator = Validator::make($request->all(), [
                'assessment_id' => 'required|exists:assessments,id',
                'subject_id' => 'required|exists:subjects,id',
                'class_section_id' => 'required|exists:class_sections,id',
                'invigilator_id' => 'required|exists:staff,id',
                'start_date' => 'required|date|after_or_equal:today',
                'end_date' => 'required|date|after_or_equal:start_date',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'status' => 'required|in:draft,active,completed',
                'venue' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }

            AssessmentSchedule::create($validator->validated());

            return redirect()->route('exam.assessment-schedules.index')->with('success', 'Assessment schedule created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create assessment schedule: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to create assessment schedule.');
        }
    }

    /**
     * Display the specified assessment schedule.
     *
     * @param AssessmentSchedule $assessmentSchedule The assessment schedule instance.
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function show(AssessmentSchedule $assessmentSchedule)
    {
        try {
            permitted('view-assessment-schedules');

            $school = GetSchoolModel();
            if (!$school || $assessmentSchedule->school_id !== $school->id) {
                abort(403, 'Unauthorized access to assessment schedule.');
            }

            return Inertia::render('Exam/AssessmentSchedules/Show', [
                'schedule' => [
                    'id' => $assessmentSchedule->id,
                    'assessment' => $assessmentSchedule->assessment?->name,
                    'subject' => $assessmentSchedule->subject?->name,
                    'class_section' => $assessmentSchedule->classSection?->name,
                    'invigilator' => $assessmentSchedule->invigilator?->name,
                    'start_date' => $assessmentSchedule->start_date->format('Y-m-d'),
                    'end_date' => $assessmentSchedule->end_date->format('Y-m-d'),
                    'start_time' => $assessmentSchedule->start_time->format('H:i'),
                    'end_time' => $assessmentSchedule->end_time->format('H:i'),
                    'status' => $assessmentSchedule->status,
                    'venue' => $assessmentSchedule->venue,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to show assessment schedule: ' . $e->getMessage());
            return redirect()->route('exam.assessment-schedules.index')->with('error', 'Failed to load assessment schedule.');
        }
    }

    /**
     * Show the form for editing the specified assessment schedule.
     *
     * @param AssessmentSchedule $assessmentSchedule The assessment schedule instance.
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function edit(AssessmentSchedule $assessmentSchedule)
    {
        try {
            permitted('edit-assessment-schedules');

            $school = GetSchoolModel();
            if (!$school || $assessmentSchedule->school_id !== $school->id) {
                abort(403, 'Unauthorized access to assessment schedule.');
            }

            $assessments = Assessment::where('school_id', $school->id)->pluck('name', 'id');
            $subjects = Subject::where('school_id', $school->id)->pluck('name', 'id');
            $classSections = ClassSection::where('school_id', $school->id)->pluck('name', 'id');
            $staff = Staff::where('school_id', $school->id)->pluck('name', 'id');

            return Inertia::render('Exam/AssessmentSchedules/Edit', [
                'schedule' => [
                    'id' => $assessmentSchedule->id,
                    'assessment_id' => $assessmentSchedule->assessment_id,
                    'subject_id' => $assessmentSchedule->subject_id,
                    'class_section_id' => $assessmentSchedule->class_section_id,
                    'invigilator_id' => $assessmentSchedule->invigilator_id,
                    'start_date' => $assessmentSchedule->start_date->format('Y-m-d'),
                    'end_date' => $assessmentSchedule->end_date->format('Y-m-d'),
                    'start_time' => $assessmentSchedule->start_time->format('H:i'),
                    'end_time' => $assessmentSchedule->end_time->format('H:i'),
                    'status' => $assessmentSchedule->status,
                    'venue' => $assessmentSchedule->venue,
                ],
                'assessments' => $assessments,
                'subjects' => $subjects,
                'classSections' => $classSections,
                'staff' => $staff,
            ], 'resources/js/Pages/Exam/AssessmentSchedules/Edit.vue');
        } catch (\Exception $e) {
            Log::error('Failed to load edit assessment schedule form: ' . $e->getMessage());
            return redirect()->route('exam.assessment-schedules.index')->with('error', 'Failed to load edit form.');
        }
    }

    /**
     * Update the specified assessment schedule in storage.
     *
     * @param Request $request The HTTP request instance.
     * @param AssessmentSchedule $assessmentSchedule The assessment schedule instance.
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, AssessmentSchedule $assessmentSchedule)
    {
        try {
            permitted('edit-assessment-schedules');

            $school = GetSchoolModel();
            if (!$school || $assessmentSchedule->school_id !== $school->id) {
                abort(403, 'Unauthorized access to assessment schedule.');
            }

            $validator = Validator::make($request->all(), [
                'assessment_id' => 'required|exists:assessments,id',
                'subject_id' => 'required|exists:subjects,id',
                'class_section_id' => 'required|exists:class_sections,id',
                'invigilator_id' => 'required|exists:staff,id',
                'start_date' => 'required|date|after_or_equal:today',
                'end_date' => 'required|date|after_or_equal:start_date',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'status' => 'required|in:draft,active,completed',
                'venue' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }

            $assessmentSchedule->update($validator->validated());

            return redirect()->route('exam.assessment-schedules.index')->with('success', 'Assessment schedule updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update assessment schedule: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update assessment schedule.');
        }
    }

    /**
     * Remove the specified assessment schedule from storage.
     *
     * @param AssessmentSchedule $assessmentSchedule The assessment schedule instance.
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Exception If no active school is found.
     */
    public function destroy(AssessmentSchedule $assessmentSchedule)
    {
        try {
            permitted('delete-assessment-schedules');

            $school = GetSchoolModel();
            if (!$school || $assessmentSchedule->school_id !== $school->id) {
                abort(403, 'Unauthorized access to assessment schedule.');
            }

            $assessmentSchedule->delete();

            return redirect()->route('exam.assessment-schedules.index')->with('success', 'Assessment schedule deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete assessment schedule: ' . $e->getMessage());
            return redirect()->route('exam.assessment-schedules.index')->with('error', 'Failed to delete assessment schedule.');
        }
    }
}

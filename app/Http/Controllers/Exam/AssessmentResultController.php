<?php

namespace App\Http\Controllers\Exam;

use App\Http\Controllers\Controller;
use App\Models\Academic\ClassSection;
use App\Models\Academic\Grade;
use App\Models\Academic\Student;
use App\Models\Academic\Subject;
use App\Models\Employee\Staff;
use App\Models\Exam\Assessment;
use App\Models\Exam\AssessmentResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

/**
 * Controller for managing assessment results in the school management system.
 */
class AssessmentResultController extends Controller
{
    /**
     * Display a listing of assessment results.
     *
     * @param Request $request The HTTP request instance.
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function index(Request $request)
    {
        try {
            permitted('view-assessment-results');

            $school = GetSchoolModel();
            if (!$school) {
                abort(403, 'No active school found.');
            }

            $query = AssessmentResult::query();

            if ($request->has('noFallback')) {
                $query->withoutFallback();
            }

            $results = $query->map(function ($result) {
                return [
                    'id' => $result->id,
                    'assessment' => $result->assessment?->name,
                    'student' => $result->student?->name,
                    'subject' => $result->subject?->name,
                    'grade' => $result->grade?->name,
                    'result' => $result->result,
                    'remark' => $result->remark,
                    'class_section' => $result->classSection?->name,
                    'graded_by' => $result->gradedBy?->name,
                ];
            });

            $assessments = Assessment::where('school_id', $school->id)->pluck('name', 'id');
            $students = Student::where('school_id', $school->id)->pluck('name', 'id');
            $subjects = Subject::where('school_id', $school->id)->pluck('name', 'id');
            $grades = Grade::where('school_id', $school->id)->pluck('name', 'id');
            $classSections = ClassSection::where('school_id', $school->id)->pluck('name', 'id');
            $staff = Staff::where('school_id', $school->id)->pluck('name', 'id');

            return Inertia::render('Exam/AssessmentResults', [
                'results' => $results,
                'assessments' => $assessments,
                'students' => $students,
                'subjects' => $subjects,
                'grades' => $grades,
                'classSections' => $classSections,
                'staff' => $staff,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch assessment results: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load assessment results.');
        }
    }

    /**
     * Show the form for creating a new assessment result.
     *
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function create()
    {
        try {
            permitted('create-assessment-results');

            $school = GetSchoolModel();
            if (!$school) {
                abort(403, 'No active school found.');
            }

            $assessments = Assessment::where('school_id', $school->id)->pluck('name', 'id');
            $students = Student::where('school_id', $school->id)->pluck('name', 'id');
            $subjects = Subject::where('school_id', $school->id)->pluck('name', 'id');
            $grades = Grade::where('school_id', $school->id)->pluck('name', 'id');
            $classSections = ClassSection::where('school_id', $school->id)->pluck('name', 'id');
            $staff = Staff::where('school_id', $school->id)->pluck('name', 'id');

            return Inertia::render('Exam/AssessmentResults/Create', [
                'assessments' => $assessments,
                'students' => $students,
                'subjects' => $subjects,
                'grades' => $grades,
                'classSections' => $classSections,
                'staff' => $staff,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load create assessment result form: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load create form.');
        }
    }

    /**
     * Store a newly created assessment result in storage.
     *
     * @param Request $request The HTTP request instance.
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        try {
            permitted('create-assessment-results');

            $school = GetSchoolModel();
            if (!$school) {
                abort(403, 'No active school found.');
            }

            $validator = Validator::make($request->all(), [
                'assessment_id' => 'required|exists:assessments,id',
                'student_id' => 'required|exists:students,id',
                'subject_id' => 'required|exists:subjects,id',
                'grade_id' => 'nullable|exists:grades,id',
                'result' => 'required|string|max:255',
                'remark' => 'nullable|string|max:255',
                'class_section_id' => 'required|exists:class_sections,id',
                'graded_by' => 'required|exists:staff,id',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }

            AssessmentResult::create($validator->validated());

            return redirect()->route('exam.assessment-results.index')->with('success', 'Assessment result created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create assessment result: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to create assessment result.');
        }
    }

    /**
     * Display the specified assessment result.
     *
     * @param AssessmentResult $assessmentResult The assessment result instance.
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function show(AssessmentResult $assessmentResult)
    {
        try {
            permitted('view-assessment-results');

            $school = GetSchoolModel();
            if (!$school || $assessmentResult->school_id !== $school->id) {
                abort(403, 'Unauthorized access to assessment result.');
            }

            return Inertia::render('Exam/AssessmentResults/Show', [
                'result' => [
                    'id' => $assessmentResult->id,
                    'assessment' => $assessmentResult->assessment?->name,
                    'student' => $assessmentResult->student?->name,
                    'subject' => $assessmentResult->subject?->name,
                    'grade' => $assessmentResult->grade?->name,
                    'result' => $assessmentResult->result,
                    'remark' => $assessmentResult->remark,
                    'class_section' => $assessmentResult->classSection?->name,
                    'graded_by' => $assessmentResult->gradedBy?->name,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to show assessment result: ' . $e->getMessage());
            return redirect()->route('exam.assessment-results.index')->with('error', 'Failed to load assessment result.');
        }
    }

    /**
     * Show the form for editing the specified assessment result.
     *
     * @param AssessmentResult $assessmentResult The assessment result instance.
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function edit(AssessmentResult $assessmentResult)
    {
        try {
            permitted('edit-assessment-results');

            $school = GetSchoolModel();
            if (!$school || $assessmentResult->school_id !== $school->id) {
                abort(403, 'Unauthorized access to assessment result.');
            }

            $assessments = Assessment::where('school_id', $school->id)->pluck('name', 'id');
            $students = Student::where('school_id', $school->id)->pluck('name', 'id');
            $subjects = Subject::where('school_id', $school->id)->pluck('name', 'id');
            $grades = Grade::where('school_id', $school->id)->pluck('name', 'id');
            $classSections = ClassSection::where('school_id', $school->id)->pluck('name', 'id');
            $staff = Staff::where('school_id', $school->id)->pluck('name', 'id');

            return Inertia::render('Exam/AssessmentResults/Edit', [
                'result' => [
                    'id' => $assessmentResult->id,
                    'assessment_id' => $assessmentResult->assessment_id,
                    'student_id' => $assessmentResult->student_id,
                    'subject_id' => $assessmentResult->subject_id,
                    'grade_id' => $assessmentResult->grade_id,
                    'result' => $assessmentResult->result,
                    'remark' => $assessmentResult->remark,
                    'class_section_id' => $assessmentResult->class_section_id,
                    'graded_by' => $assessmentResult->graded_by,
                ],
                'assessments' => $assessments,
                'students' => $students,
                'subjects' => $subjects,
                'grades' => $grades,
                'classSections' => $classSections,
                'staff' => $staff,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load edit assessment result form: ' . $e->getMessage());
            return redirect()->route('exam.assessment-results.index')->with('error', 'Failed to load edit form.');
        }
    }

    /**
     * Update the specified assessment result in storage.
     *
     * @param Request $request The HTTP request instance.
     * @param AssessmentResult $assessmentResult The assessment result instance.
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, AssessmentResult $assessmentResult)
    {
        try {
            permitted('assessment-results.edit');

            $school = GetSchoolModel();
            if (!$school || $assessmentResult->school_id !== $school->id) {
                abort(403, 'Unauthorized access to assessment result.');
            }

            $validator = Validator::make($request->all(), [
                'assessment_id' => 'required|exists:assessments,id',
                'student_id' => 'required|exists:students,id',
                'subject_id' => 'required|exists:subjects,id',
                'grade_id' => 'nullable|exists:grades,id',
                'result' => 'required|string|max:255',
                'remark' => 'nullable|string|max:255',
                'class_section_id' => 'required|exists:class_sections,id',
                'graded_by' => 'required|exists:staff,id',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }

            $assessmentResult->update($validator->validated());

            return redirect()->route('exam.assessment-results.index')->with('success', 'Assessment result updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update assessment result: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update assessment result.');
        }
    }

    /**
     * Remove the specified assessment result from storage.
     *
     * @param AssessmentResult $assessmentResult The assessment result instance.
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Exception If no active school is found.
     */
    public function destroy(AssessmentResult $assessmentResult)
    {
        try {
            permitted('assessment-results.delete');

            $school = GetSchoolModel();
            if (!$school || $assessmentResult->school_id !== $school->id) {
                abort(403, 'Unauthorized access to assessment result.');
            }

            $assessmentResult->delete();

            return redirect()->route('exam.assessment-results.index')->with('success', 'Assessment result deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete assessment result: ' . $e->getMessage());
            return redirect()->route('exam.assessment-results.index')->with('error', 'Failed to delete assessment result.');
        }
    }
}

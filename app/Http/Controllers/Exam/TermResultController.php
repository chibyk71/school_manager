<?php

namespace App\Http\Controllers;

use App\Models\Academic\ClassLevel;
use App\Models\Academic\Student;
use App\Models\Academic\Term;
use App\Models\Exam\TermResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

/**
 * Controller for managing term results in the school management system.
 */
class TermResultController extends Controller
{
    /**
     * Display a listing of term results.
     *
     * @param Request $request The HTTP request instance.
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function index(Request $request)
    {
        try {
            permitted('view-term-results');

            $school = GetSchoolModel();
            if (!$school) {
                abort(403, 'No active school found.');
            }

            $query = TermResult::query();

            if ($request->has('noFallback')) {
                $query->withoutFallback();
            }

            $results = $query->map(function ($result) {
                return [
                    'id' => $result->id,
                    'student' => $result->student?->name,
                    'term' => $result->term?->name,
                    'class' => $result->classLevel?->name,
                    'total_score' => $result->total_score,
                    'average_score' => $result->average_score,
                    'position' => $result->position,
                    'class_teacher_remark' => $result->class_teacher_remark,
                    'head_teacher_remark' => $result->head_teacher_remark,
                    'grade' => $result->grade,
                ];
            });

            $students = Student::where('school_id', $school->id)->pluck('name', 'id');
            $terms = Term::where('school_id', $school->id)->pluck('name', 'id');
            $classLevels = ClassLevel::where('school_id', $school->id)->pluck('name', 'id');

            return Inertia::render('Exam/TermResults', [
                'results' => $results,
                'students' => $students,
                'terms' => $terms,
                'classLevels' => $classLevels,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch term results: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load term results.');
        }
    }

    /**
     * Show the form for creating a new term result.
     *
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function create()
    {
        try {
            permitted('create-term-results');

            $school = GetSchoolModel();
            if (!$school) {
                abort(403, 'No active school found.');
            }

            $students = Student::where('school_id', $school->id)->pluck('name', 'id');
            $terms = Term::where('school_id', $school->id)->pluck('name', 'id');
            $classLevels = ClassLevel::where('school_id', $school->id)->pluck('name', 'id');

            return Inertia::render('Exam/TermResults/Create', [
                'students' => $students,
                'terms' => $terms,
                'classLevels' => $classLevels,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load create term result form: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load create form.');
        }
    }

    /**
     * Store a newly created term result in storage.
     *
     * @param Request $request The HTTP request instance.
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        try {
            permitted('create-term-results');

            $school = GetSchoolModel();
            if (!$school) {
                abort(403, 'No active school found.');
            }

            $validator = Validator::make($request->all(), [
                'student_id' => 'required|exists:students,id',
                'term_id' => 'required|exists:terms,id',
                'class_id' => 'required|exists:class_levels,id',
                'total_score' => 'required|numeric|min:0',
                'average_score' => 'required|numeric|min:0',
                'position' => 'required|integer|min:1',
                'class_teacher_remark' => 'nullable|string',
                'head_teacher_remark' => 'nullable|string',
                'grade' => 'required|string|max:10',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }

            TermResult::create(array_merge($validator->validated(), ['school_id' => $school->id]));

            return redirect()->route('exam.term-results.index')->with('success', 'Term result created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create term result: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to create term result.');
        }
    }

    /**
     * Display the specified term result.
     *
     * @param TermResult $termResult The term result instance.
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function show(TermResult $termResult)
    {
        try {
            permitted('view-term-results');

            $school = GetSchoolModel();
            if (!$school || $termResult->school_id !== $school->id) {
                abort(403, 'Unauthorized access to term result.');
            }

            return Inertia::render('Exam/TermResults/Show', [
                'result' => [
                    'id' => $termResult->id,
                    'student' => $termResult->student?->name,
                    'term' => $termResult->term?->name,
                    'class' => $termResult->classLevel?->name,
                    'total_score' => $termResult->total_score,
                    'average_score' => $termResult->average_score,
                    'position' => $termResult->position,
                    'class_teacher_remark' => $termResult->class_teacher_remark,
                    'head_teacher_remark' => $termResult->head_teacher_remark,
                    'grade' => $termResult->grade,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to show term result: ' . $e->getMessage());
            return redirect()->route('exam.term-results.index')->with('error', 'Failed to load term result.');
        }
    }

    /**
     * Show the form for editing the specified term result.
     *
     * @param TermResult $termResult The term result instance.
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function edit(TermResult $termResult)
    {
        try {
            permitted('edit-term-results');

            $school = GetSchoolModel();
            if (!$school || $termResult->school_id !== $school->id) {
                abort(403, 'Unauthorized access to term result.');
            }

            $students = Student::where('school_id', $school->id)->pluck('name', 'id');
            $terms = Term::where('school_id', $school->id)->pluck('name', 'id');
            $classLevels = ClassLevel::where('school_id', $school->id)->pluck('name', 'id');

            return Inertia::render('Exam/TermResults/Edit', [
                'result' => [
                    'id' => $termResult->id,
                    'student_id' => $termResult->student_id,
                    'term_id' => $termResult->term_id,
                    'class_id' => $termResult->class_id,
                    'total_score' => $termResult->total_score,
                    'average_score' => $termResult->average_score,
                    'position' => $termResult->position,
                    'class_teacher_remark' => $termResult->class_teacher_remark,
                    'head_teacher_remark' => $termResult->head_teacher_remark,
                    'grade' => $termResult->grade,
                ],
                'students' => $students,
                'terms' => $terms,
                'classLevels' => $classLevels,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load edit term result form: ' . $e->getMessage());
            return redirect()->route('exam.term-results.index')->with('error', 'Failed to load edit form.');
        }
    }

    /**
     * Update the specified term result in storage.
     *
     * @param Request $request The HTTP request instance.
     * @param TermResult $termResult The term result instance.
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, TermResult $termResult)
    {
        try {
            permitted('edit-term-results');

            $school = GetSchoolModel();
            if (!$school || $termResult->school_id !== $school->id) {
                abort(403, 'Unauthorized access to term result.');
            }

            $validator = Validator::make($request->all(), [
                'student_id' => 'required|exists:students,id',
                'term_id' => 'required|exists:terms,id',
                'class_id' => 'required|exists:class_levels,id',
                'total_score' => 'required|numeric|min:0',
                'average_score' => 'required|numeric|min:0',
                'position' => 'required|integer|min:1',
                'class_teacher_remark' => 'nullable|string',
                'head_teacher_remark' => 'nullable|string',
                'grade' => 'required|string|max:10',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }

            $termResult->update($validator->validated());

            return redirect()->route('exam.term-results.index')->with('success', 'Term result updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update term result: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update term result.');
        }
    }

    /**
     * Remove the specified term result from storage.
     *
     * @param TermResult $termResult The term result instance.
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Exception If no active school is found.
     */
    public function destroy(TermResult $termResult)
    {
        try {
            permitted('delete-term-results');

            $school = GetSchoolModel();
            if (!$school || $termResult->school_id !== $school->id) {
                abort(403, 'Unauthorized access to term result.');
            }

            $termResult->delete();

            return redirect()->route('exam.term-results.index')->with('success', 'Term result deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete term result: ' . $e->getMessage());
            return redirect()->route('exam.term-results.index')->with('error', 'Failed to delete term result.');
        }
    }
}

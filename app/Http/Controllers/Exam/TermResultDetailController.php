<?php

namespace App\Http\Controllers\Exam;

use App\Http\Controllers\Controller;
use App\Models\Academic\Grade;
use App\Models\Academic\Subject;
use App\Models\Exam\TermResult;
use App\Models\Exam\TermResultDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

/**
 * Controller for managing term result details in the school management system.
 */
class TermResultDetailController extends Controller
{
    /**
     * Display a listing of term result details for a specific term result.
     *
     * @param Request $request The HTTP request instance.
     * @param TermResult $termResult The parent term result instance.
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function index(Request $request, TermResult $termResult)
    {
        try {
            permitted('view-term-result-details');

            $school = GetSchoolModel();
            if (!$school || $termResult->school_id !== $school->id) {
                abort(403, 'Unauthorized access to term result.');
            }

            $query = TermResultDetail::query()->where('term_result_id', $termResult->id);

            if ($request->has('noFallback')) {
                $query->withoutFallback();
            }

            $details = $query->map(function ($detail) {
                return [
                    'id' => $detail->id,
                    'term_result_id' => $detail->term_result_id,
                    'subject' => $detail->subject?->name,
                    'grade' => $detail->grade?->name,
                    'score' => $detail->score,
                    'class_teacher_remark' => $detail->class_teacher_remark,
                    'head_teacher_remark' => $detail->head_teacher_remark,
                ];
            });

            $subjects = Subject::where('school_id', $school->id)->pluck('name', 'id');
            $grades = Grade::where('school_id', $school->id)->pluck('name', 'id');

            return Inertia::render('Exam/TermResultDetails', [
                'termResult' => [
                    'id' => $termResult->id,
                    'student' => $termResult->student?->name,
                    'term' => $termResult->term?->name,
                ],
                'details' => $details,
                'subjects' => $subjects,
                'grades' => $grades,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch term result details: ' . $e->getMessage());
            return redirect()->route('exam.term-results.index')->with('error', 'Failed to load term result details.');
        }
    }

    /**
     * Show the form for creating a new term result detail.
     *
     * @param TermResult $termResult The parent term result instance.
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function create(TermResult $termResult)
    {
        try {
            permitted('create-term-result-details');

            $school = GetSchoolModel();
            if (!$school || $termResult->school_id !== $school->id) {
                abort(403, 'Unauthorized access to term result.');
            }

            $subjects = Subject::where('school_id', $school->id)->pluck('name', 'id');
            $grades = Grade::where('school_id', $school->id)->pluck('name', 'id');

            return Inertia::render('Exam/TermResultDetails/Create', [
                'termResult' => [
                    'id' => $termResult->id,
                    'student' => $termResult->student?->name,
                    'term' => $termResult->term?->name,
                ],
                'subjects' => $subjects,
                'grades' => $grades,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load create term result detail form: ' . $e->getMessage());
            return redirect()->route('exam.term-results.index')->with('error', 'Failed to load create form.');
        }
    }

    /**
     * Store a newly created term result detail in storage.
     *
     * @param Request $request The HTTP request instance.
     * @param TermResult $termResult The parent term result instance.
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request, TermResult $termResult)
    {
        try {
            permitted('create-term-result-details');

            $school = GetSchoolModel();
            if (!$school || $termResult->school_id !== $school->id) {
                abort(403, 'Unauthorized access to term result.');
            }

            $validator = Validator::make($request->all(), [
                'subject_id' => 'required|exists:subjects,id',
                'grade_id' => 'required|exists:grades,id',
                'score' => 'required|numeric|min:0|max:100',
                'class_teacher_remark' => 'nullable|string',
                'head_teacher_remark' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }

            TermResultDetail::create(array_merge($validator->validated(), [
                'school_id' => $school->id,
                'term_result_id' => $termResult->id,
            ]));

            return redirect()->route('exam.term-results.details.index', $termResult)
                ->with('success', 'Term result detail created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create term result detail: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to create term result detail.');
        }
    }

    /**
     * Display the specified term result detail.
     *
     * @param TermResult $termResult The parent term result instance.
     * @param TermResultDetail $termResultDetail The term result detail instance.
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function show(TermResult $termResult, TermResultDetail $termResultDetail)
    {
        try {
            permitted('view-term-result-details');

            $school = GetSchoolModel();
            if (!$school || $termResult->school_id !== $school->id || $termResultDetail->school_id !== $school->id) {
                abort(403, 'Unauthorized access to term result detail.');
            }

            if ($termResultDetail->term_result_id !== $termResult->id) {
                abort(403, 'Term result detail does not belong to this term result.');
            }

            return Inertia::render('Exam/TermResultDetails/Show', [
                'termResult' => [
                    'id' => $termResult->id,
                    'student' => $termResult->student?->name,
                    'term' => $termResult->term?->name,
                ],
                'detail' => [
                    'id' => $termResultDetail->id,
                    'subject' => $termResultDetail->subject?->name,
                    'grade' => $termResultDetail->grade?->name,
                    'score' => $termResultDetail->score,
                    'class_teacher_remark' => $termResultDetail->class_teacher_remark,
                    'head_teacher_remark' => $termResultDetail->head_teacher_remark,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to show term result detail: ' . $e->getMessage());
            return redirect()->route('exam.term-results.index')->with('error', 'Failed to load term result detail.');
        }
    }

    /**
     * Show the form for editing the specified term result detail.
     *
     * @param TermResult $termResult The parent term result instance.
     * @param TermResultDetail $termResultDetail The term result detail instance.
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function edit(TermResult $termResult, TermResultDetail $termResultDetail)
    {
        try {
            permitted('edit-term-result-details');

            $school = GetSchoolModel();
            if (!$school || $termResult->school_id !== $school->id || $termResultDetail->school_id !== $school->id) {
                abort(403, 'Unauthorized access to term result detail.');
            }

            if ($termResultDetail->term_result_id !== $termResult->id) {
                abort(403, 'Term result detail does not belong to this term result.');
            }

            $subjects = Subject::where('school_id', $school->id)->pluck('name', 'id');
            $grades = Grade::where('school_id', $school->id)->pluck('name', 'id');

            return Inertia::render('Exam/TermResultDetails/Edit', [
                'termResult' => [
                    'id' => $termResult->id,
                    'student' => $termResult->student?->name,
                    'term' => $termResult->term?->name,
                ],
                'detail' => [
                    'id' => $termResultDetail->id,
                    'subject_id' => $termResultDetail->subject_id,
                    'grade_id' => $termResultDetail->grade_id,
                    'score' => $termResultDetail->score,
                    'class_teacher_remark' => $termResultDetail->class_teacher_remark,
                    'head_teacher_remark' => $termResultDetail->head_teacher_remark,
                ],
                'subjects' => $subjects,
                'grades' => $grades,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load edit term result detail form: ' . $e->getMessage());
            return redirect()->route('exam.term-results.index')->with('error', 'Failed to load edit form.');
        }
    }

    /**
     * Update the specified term result detail in storage.
     *
     * @param Request $request The HTTP request instance.
     * @param TermResult $termResult The parent term result instance.
     * @param TermResultDetail $termResultDetail The term result detail instance.
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, TermResult $termResult, TermResultDetail $termResultDetail)
    {
        try {
            permitted('edit-term-result-details');

            $school = GetSchoolModel();
            if (!$school || $termResult->school_id !== $school->id || $termResultDetail->school_id !== $school->id) {
                abort(403, 'Unauthorized access to term result detail.');
            }

            if ($termResultDetail->term_result_id !== $termResult->id) {
                abort(403, 'Term result detail does not belong to this term result.');
            }

            $validator = Validator::make($request->all(), [
                'subject_id' => 'required|exists:subjects,id',
                'grade_id' => 'required|exists:grades,id',
                'score' => 'required|numeric|min:0|max:100',
                'class_teacher_remark' => 'nullable|string',
                'head_teacher_remark' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }

            $termResultDetail->update($validator->validated());

            return redirect()->route('exam.term-results.details.index', $termResult)
                ->with('success', 'Term result detail updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update term result detail: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update term result detail.');
        }
    }

    /**
     * Remove the specified term result detail from storage.
     *
     * @param TermResult $termResult The parent term result instance.
     * @param TermResultDetail $termResultDetail The term result detail instance.
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Exception If no active school is found.
     */
    public function destroy(TermResult $termResult, TermResultDetail $termResultDetail)
    {
        try {
            permitted('delete-term-result-details');

            $school = GetSchoolModel();
            if (!$school || $termResult->school_id !== $school->id || $termResultDetail->school_id !== $school->id) {
                abort(403, 'Unauthorized access to term result detail.');
            }

            if ($termResultDetail->term_result_id !== $termResult->id) {
                abort(403, 'Term result detail does not belong to this term result.');
            }

            $termResultDetail->delete();

            return redirect()->route('exam.term-results.details.index', $termResult)
                ->with('success', 'Term result detail deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete term result detail: ' . $e->getMessage());
            return redirect()->route('exam.term-results.index')->with('error', 'Failed to delete term result detail.');
        }
    }
}

<?php

namespace App\Http\Controllers\Exam;

use App\Http\Controllers\Controller;
use App\Models\Academic\Term;
use App\Models\Exam\Assessment;
use App\Models\Exam\AssessmentType;
use App\Models\SchoolSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

/**
 * Controller for managing assessments in the school management system.
 */
class AssessmentController extends Controller
{
    /**
     * Display a listing of assessments.
     *
     * @param Request $request The HTTP request instance.
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function index(Request $request)
    {
        try {
            permitted('view-assessments');

            $school = GetSchoolModel();
            if (!$school) {
                abort(403, 'No active school found.');
            }

            $query = Assessment::tableQuery($request, [
                'assessment_type.name' => [
                    'field' => 'assessment_type.name',
                    'header' => 'Type',
                    'sortable' => true,
                    'filterable' => true,
                    'filterType' => 'text',
                    'relation' => 'assessmentType',
                    'relatedField' => 'name',
                ],
                'term.name' => [
                    'field' => 'term.name',
                    'header' => 'Term',
                    'sortable' => true,
                    'filterable' => true,
                    'filterType' => 'text',
                    'relation' => 'term',
                    'relatedField' => 'name',
                ],
            ]);

            if ($request->has('noFallback')) {
                $query->withoutFallback();
            }

            $assessments = $query->map(function ($assessment) {
                return [
                    'id' => $assessment->id,
                    'name' => $assessment->name,
                    'type' => $assessment->type,
                    'term' => $assessment->term?->name,
                    'weight' => $assessment->weight,
                    'max_score' => $assessment->max_score,
                    'date_effective' => $assessment->date_effective->format('Y-m-d'),
                    'date_due' => $assessment->date_due->format('Y-m-d'),
                    'published_at' => $assessment->published_at?->format('Y-m-d H:i'),
                    'instruction' => $assessment->instruction,
                    'sections' => $assessment->schoolSections->pluck('name'),
                ];
            });

            $terms = Term::where('school_id', $school->id)->pluck('name', 'id');
            $assessmentTypes = AssessmentType::where('school_id', $school->id)->pluck('name', 'id');
            $sections = SchoolSection::where('school_id', $school->id)->pluck('name', 'id');

            return Inertia::render('Exam/Assessments', [
                'assessments' => $assessments,
                'terms' => $terms,
                'assessmentTypes' => $assessmentTypes,
                'sections' => $sections,
            ], 'resources/js/Pages/Exam/Assessments.vue');
        } catch (\Exception $e) {
            Log::error('Failed to fetch assessments: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load assessments.');
        }
    }

    /**
     * Show the form for creating a new assessment.
     *
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function create()
    {
        try {
            permitted('create-assessments');

            $school = GetSchoolModel();
            if (!$school) {
                abort(403, 'No active school found.');
            }

            $terms = Term::where('school_id', $school->id)->pluck('name', 'id');
            $assessmentTypes = AssessmentType::where('school_id', $school->id)->pluck('name', 'id');
            $sections = SchoolSection::where('school_id', $school->id)->pluck('name', 'id');

            return Inertia::render('Exam/Assessments/Create', [
                'terms' => $terms,
                'assessmentTypes' => $assessmentTypes,
                'sections' => $sections,
            ], 'resources/js/Pages/Exam/Assessments/Create.vue');
        } catch (\Exception $e) {
            Log::error('Failed to load create assessment form: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load create form.');
        }
    }

    /**
     * Store a newly created assessment in storage.
     *
     * @param Request $request The HTTP request instance.
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        try {
            permitted('create-assessments');

            $school = GetSchoolModel();
            if (!$school) {
                abort(403, 'No active school found.');
            }

            $validator = Validator::make($request->all(), [
                'assessment_type_id' => 'required|exists:assessment_types,id',
                'term_id' => 'required|exists:terms,id',
                'name' => 'required|string|max:255',
                'weight' => 'required|integer|min:0',
                'max_score' => 'required|integer|min:0',
                'date_effective' => 'required|date|after_or_equal:today',
                'date_due' => 'required|date|after:date_effective',
                'published_at' => 'nullable|date|after_or_equal:date_effective',
                'instruction' => 'nullable|string',
                'section_ids' => 'nullable|array',
                'section_ids.*' => 'exists:school_sections,id',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }

            $assessment = Assessment::create($validator->validated());
            if ($request->has('section_ids')) {
                $assessment->schoolSections()->sync($request->input('section_ids'));
            }

            return redirect()->route('exam.assessments.index')->with('success', 'Assessment created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create assessment: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to create assessment.');
        }
    }

    /**
     * Display the specified assessment.
     *
     * @param Assessment $assessment The assessment instance.
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function show(Assessment $assessment)
    {
        try {
            permitted('view-assessments');

            $school = GetSchoolModel();
            if (!$school || $assessment->school_id !== $school->id) {
                abort(403, 'Unauthorized access to assessment.');
            }

            return Inertia::render('Exam/Assessments/Show', [
                'assessment' => [
                    'id' => $assessment->id,
                    'name' => $assessment->name,
                    'type' => $assessment->type,
                    'term' => $assessment->term?->name,
                    'weight' => $assessment->weight,
                    'max_score' => $assessment->max_score,
                    'date_effective' => $assessment->date_effective->format('Y-m-d'),
                    'date_due' => $assessment->date_due->format('Y-m-d'),
                    'published_at' => $assessment->published_at?->format('Y-m-d H:i'),
                    'instruction' => $assessment->instruction,
                    'sections' => $assessment->schoolSections->pluck('name', 'id'),
                ],
            ], 'resources/js/Pages/Exam/Assessments/Show.vue');
        } catch (\Exception $e) {
            Log::error('Failed to show assessment: ' . $e->getMessage());
            return redirect()->route('exam.assessments.index')->with('error', 'Failed to load assessment.');
        }
    }

    /**
     * Show the form for editing the specified assessment.
     *
     * @param Assessment $assessment The assessment instance.
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function edit(Assessment $assessment)
    {
        try {
            permitted('edit-assessments');

            $school = GetSchoolModel();
            if (!$school || $assessment->school_id !== $school->id) {
                abort(403, 'Unauthorized access to assessment.');
            }

            $terms = Term::where('school_id', $school->id)->pluck('name', 'id');
            $assessmentTypes = AssessmentType::where('school_id', $school->id)->pluck('name', 'id');
            $sections = SchoolSection::where('school_id', $school->id)->pluck('name', 'id');

            return Inertia::render('Exam/Assessments/Edit', [
                'assessment' => [
                    'id' => $assessment->id,
                    'assessment_type_id' => $assessment->assessment_type_id,
                    'term_id' => $assessment->term_id,
                    'name' => $assessment->name,
                    'weight' => $assessment->weight,
                    'max_score' => $assessment->max_score,
                    'date_effective' => $assessment->date_effective->format('Y-m-d'),
                    'date_due' => $assessment->date_due->format('Y-m-d'),
                    'published_at' => $assessment->published_at?->format('Y-m-d H:i'),
                    'instruction' => $assessment->instruction,
                    'section_ids' => $assessment->schoolSections->pluck('id'),
                ],
                'terms' => $terms,
                'assessmentTypes' => $assessmentTypes,
                'sections' => $sections,
            ], 'resources/js/Pages/Exam/Assessments/Edit.vue');
        } catch (\Exception $e) {
            Log::error('Failed to load edit assessment form: ' . $e->getMessage());
            return redirect()->route('exam.assessments.index')->with('error', 'Failed to load edit form.');
        }
    }

    /**
     * Update the specified assessment in storage.
     *
     * @param Request $request The HTTP request instance.
     * @param Assessment $assessment The assessment instance.
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, Assessment $assessment)
    {
        try {
            permitted('edit-assessments');

            $school = GetSchoolModel();
            if (!$school || $assessment->school_id !== $school->id) {
                abort(403, 'Unauthorized access to assessment.');
            }

            $validator = Validator::make($request->all(), [
                'assessment_type_id' => 'required|exists:assessment_types,id',
                'term_id' => 'required|exists:terms,id',
                'name' => 'required|string|max:255',
                'weight' => 'required|integer|min:0',
                'max_score' => 'required|integer|min:0',
                'date_effective' => 'required|date|after_or_equal:today',
                'date_due' => 'required|date|after:date_effective',
                'published_at' => 'nullable|date|after_or_equal:date_effective',
                'instruction' => 'nullable|string',
                'section_ids' => 'nullable|array',
                'section_ids.*' => 'exists:school_sections,id',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }

            $assessment->update($validator->validated());
            if ($request->has('section_ids')) {
                $assessment->schoolSections()->sync($request->input('section_ids'));
            }

            return redirect()->route('exam.assessments.index')->with('success', 'Assessment updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update assessment: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update assessment.');
        }
    }

    /**
     * Remove the specified assessment from storage.
     *
     * @param Assessment $assessment The assessment instance.
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Exception If no active school is found.
     */
    public function destroy(Assessment $assessment)
    {
        try {
            permitted('delete-assessments');

            $school = GetSchoolModel();
            if (!$school || $assessment->school_id !== $school->id) {
                abort(403, 'Unauthorized access to assessment.');
            }

            $assessment->delete();

            return redirect()->route('exam.assessments.index')->with('success', 'Assessment deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete assessment: ' . $e->getMessage());
            return redirect()->route('exam.assessments.index')->with('error', 'Failed to delete assessment.');
        }
    }
}

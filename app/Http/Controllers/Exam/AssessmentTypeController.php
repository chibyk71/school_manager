<?php

namespace App\Http\Controllers\Exam;

use App\Http\Controllers\Controller;
use App\Models\Exam\AssessmentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

/**
 * Controller for managing assessment types in the school management system.
 */
class AssessmentTypeController extends Controller
{
    /**
     * Display a listing of assessment types.
     *
     * @param Request $request The HTTP request instance.
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function index(Request $request)
    {
        try {
            permitted('view-assessment-types');

            $school = GetSchoolModel();
            if (!$school) {
                abort(403, 'No active school found.');
            }

            $query = AssessmentType::query();

            if ($request->has('noFallback')) {
                $query->withoutFallback();
            }

            $assessmentTypes = $query->map(function ($type) {
                return [
                    'id' => $type->id,
                    'name' => $type->name,
                    'description' => $type->description,
                    'status' => $type->status,
                    'weight' => $type->weight,
                    'assessment_count' => $type->assessments()->count(),
                ];
            });

            return Inertia::render('Exam/AssessmentTypes', [
                'assessmentTypes' => $assessmentTypes,
                'statusOptions' => ['active', 'inactive'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch assessment types: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load assessment types.');
        }
    }

    /**
     * Show the form for creating a new assessment type.
     *
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function create()
    {
        try {
            permitted('create-assessment-types');

            $school = GetSchoolModel();
            if (!$school) {
                abort(403, 'No active school found.');
            }

            return Inertia::render('Exam/AssessmentTypes/Create', [
                'statusOptions' => ['active', 'inactive'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load create assessment type form: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load create form.');
        }
    }

    /**
     * Store a newly created assessment type in storage.
     *
     * @param Request $request The HTTP request instance.
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        try {
            permitted('create-assessment-types');

            $school = GetSchoolModel();
            if (!$school) {
                abort(403, 'No active school found.');
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'status' => 'required|in:active,inactive',
                'weight' => 'required|integer|min:0',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }

            AssessmentType::create(array_merge($validator->validated(), ['school_id' => $school->id]));

            return redirect()->route('exam.assessment-types.index')->with('success', 'Assessment type created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create assessment type: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to create assessment type.');
        }
    }

    /**
     * Display the specified assessment type.
     *
     * @param AssessmentType $assessmentType The assessment type instance.
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function show(AssessmentType $assessmentType)
    {
        try {
            permitted('view-assessment-types');

            $school = GetSchoolModel();
            if (!$school || $assessmentType->school_id !== $school->id) {
                abort(403, 'Unauthorized access to assessment type.');
            }

            return Inertia::render('Exam/AssessmentTypes/Show', [
                'assessmentType' => [
                    'id' => $assessmentType->id,
                    'name' => $assessmentType->name,
                    'description' => $assessmentType->description,
                    'status' => $assessmentType->status,
                    'weight' => $assessmentType->weight,
                    'assessment_count' => $assessmentType->assessments()->count(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to show assessment type: ' . $e->getMessage());
            return redirect()->route('exam.assessment-types.index')->with('error', 'Failed to load assessment type.');
        }
    }

    /**
     * Show the form for editing the specified assessment type.
     *
     * @param AssessmentType $assessmentType The assessment type instance.
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function edit(AssessmentType $assessmentType)
    {
        try {
            permitted('edit-assessment-types');

            $school = GetSchoolModel();
            if (!$school || $assessmentType->school_id !== $school->id) {
                abort(403, 'Unauthorized access to assessment type.');
            }

            return Inertia::render('Exam/AssessmentTypes/Edit', [
                'assessmentType' => [
                    'id' => $assessmentType->id,
                    'name' => $assessmentType->name,
                    'description' => $assessmentType->description,
                    'status' => $assessmentType->status,
                    'weight' => $assessmentType->weight,
                ],
                'statusOptions' => ['active', 'inactive'],
            ], 'resources/js/Pages/Exam/AssessmentTypes/Edit.vue');
        } catch (\Exception $e) {
            Log::error('Failed to load edit assessment type form: ' . $e->getMessage());
            return redirect()->route('exam.assessment-types.index')->with('error', 'Failed to load edit form.');
        }
    }

    /**
     * Update the specified assessment type in storage.
     *
     * @param Request $request The HTTP request instance.
     * @param AssessmentType $assessmentType The assessment type instance.
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, AssessmentType $assessmentType)
    {
        try {
            permitted('edit-assessment-types');

            $school = GetSchoolModel();
            if (!$school || $assessmentType->school_id !== $school->id) {
                abort(403, 'Unauthorized access to assessment type.');
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'status' => 'required|in:active,inactive',
                'weight' => 'required|integer|min:0',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }

            $assessmentType->update($validator->validated());

            return redirect()->route('exam.assessment-types.index')->with('success', 'Assessment type updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update assessment type: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update assessment type.');
        }
    }

    /**
     * Remove the specified assessment type from storage.
     *
     * @param AssessmentType $assessmentType The assessment type instance.
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Exception If no active school is found.
     */
    public function destroy(AssessmentType $assessmentType)
    {
        try {
            permitted('delete-assessment-types');

            $school = GetSchoolModel();
            if (!$school || $assessmentType->school_id !== $school->id) {
                abort(403, 'Unauthorized access to assessment type.');
            }

            if ($assessmentType->assessments()->exists()) {
                return redirect()->route('exam.assessment-types.index')->with('error', 'Cannot delete assessment type with associated assessments.');
            }

            $assessmentType->delete();

            return redirect()->route('exam.assessment-types.index')->with('success', 'Assessment type deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete assessment type: ' . $e->getMessage());
            return redirect()->route('exam.assessment-types.index')->with('error', 'Failed to delete assessment type.');
        }
    }
}

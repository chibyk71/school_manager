<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubjectRequest;
use App\Http\Requests\UpdateSubjectRequest;
use App\Models\Academic\Subject;
use App\Models\SchoolSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Controller for managing Subject resources.
 */
class SubjectController extends Controller
{
    /**
     * Display a listing of subjects with dynamic querying.
     *
     * @param Request $request
     * @param SchoolSection|null $schoolSection
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request, ?SchoolSection $schoolSection = null)
    {
        permitted('subjects.view', $request->wantsJson()); // Check permission

        try {
            // Define extra fields for table query (e.g., related school section names)
            $extraFields = [
                [
                    'field' => 'school_section_names',
                    'relation' => 'schoolSections',
                    'relatedField' => 'name',
                    'filterable' => true,
                    'sortable' => false,
                    'filterType' => 'text',
                ],
            ];

            // Build query
            $query = Subject::with(['schoolSections:id,name'])
                ->when($schoolSection, fn($q) => $q->inSection($schoolSection->id))
                ->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Apply dynamic table query (search, filter, sort, paginate)
            $subjects = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($subjects);
            }

            return Inertia::render('Academic/Subjects', [
                'schoolSection' => $schoolSection ? $schoolSection->only('id', 'name') : null,
                'subjects' => $subjects,
                'schoolSections' => SchoolSection::select('id', 'name')->get(), // For dropdowns in UI
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch subjects: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch subjects'], 500)
                : redirect()->back()->with(['error' => 'Failed to fetch subjects']);
        }
    }

    /**
     * Store a newly created subject in storage.
     *
     * @param StoreSubjectRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(StoreSubjectRequest $request)
    {
        permitted('subjects.create', $request->wantsJson()); // Check permission

        try {
            $validated = $request->validated();
            $school = GetSchoolModel();
            $validated['school_id'] = $school->id; // Ensure school_id is set
            $subject = Subject::create($validated);
            if (!empty($validated['school_section'])) {
                $subject->attachSections($validated['school_section']);
            }

            return $request->wantsJson()
                ? response()->json(['message' => 'Subject created successfully'], 201)
                : redirect()->route('subjects.index')->with(['success' => 'Subject created successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to create subject: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create subject'], 500)
                : redirect()->back()->with(['error' => 'Failed to create subject'])->withInput();
        }
    }

    /**
     * Display the specified subject.
     *
     * @param Request $request
     * @param Subject $subject
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Subject $subject)
    {
        permitted('subjects.view', true); // Check permission (JSON response)

        try {
            $subject->load(['schoolSections:id,name']);
            return response()->json($subject);
        } catch (\Exception $e) {
            Log::error('Failed to fetch subject: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch subject'], 500);
        }
    }

    /**
     * Update the specified subject in storage.
     *
     * @param UpdateSubjectRequest $request
     * @param Subject $subject
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(UpdateSubjectRequest $request, Subject $subject)
    {
        permitted('subjects.update', $request->wantsJson()); // Check permission

        try {
            $validated = $request->validated();
            $subject->update($validated);
            if (!empty($validated['school_section'])) {
                $subject->syncSections($validated['school_section']);
            } else {
                $subject->schoolSections()->detach(); // Remove all sections if none provided
            }

            return $request->wantsJson()
                ? response()->json(['message' => 'Subject updated successfully'])
                : redirect()->route('subjects.index')->with(['success' => 'Subject updated successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to update subject: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update subject'], 500)
                : redirect()->back()->with(['error' => 'Failed to update subject'])->withInput();
        }
    }

    /**
     * Remove the specified subject(s) from storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        permitted('subjects.delete', true); // Check permission (JSON response)

        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:subjects,id',
            ]);

            $forceDelete = $request->boolean('force');
            $ids = $request->input('ids');
            $deleted = $forceDelete
                ? Subject::whereIn('id', $ids)->forceDelete()
                : Subject::whereIn('id', $ids)->delete();

            return response()->json([
                'message' => $deleted ? 'Subject(s) deleted successfully' : 'No subjects were deleted',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete subjects: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to delete subject(s)'], 500);
        }
    }

    /**
     * Restore a soft-deleted subject.
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, $id)
    {
        permitted('subjects.restore', true); // Check permission (JSON response)

        try {
            $subject = Subject::withTrashed()->findOrFail($id);
            $subject->restore();

            return response()->json(['message' => 'Subject restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore subject: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore subject'], 500);
        }
    }
}
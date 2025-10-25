<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSchoolSectionRequest;
use App\Http\Requests\UpdateSchoolSectionRequest;
use App\Models\SchoolSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Controller for managing school sections in a multi-tenant system.
 */
class SchoolSectionController extends Controller
{
    /**
     * Display a listing of school sections.
     *
     * Retrieves sections for the active school and renders the section view.
     *
     * @param Request $request The incoming HTTP request.
     * @return \Inertia\Response The Inertia response with sections data.
     * @throws \Exception If section retrieval fails or school is not found.
     */
    public function index(Request $request)
    {
        try {
            permitted('view-sections');

            $school = GetSchoolModel();
            if (!$school) {
                abort(403, 'No active school found.');
            }

            $cacheKey = "sections.school_{$school->id}";

            $schoolSections = Cache::remember($cacheKey, now()->addHour(), function () use ($school, $request) {
                return SchoolSection::query()->where('school_id', $school->id)
                    ->with('school:id,name')
                    ->get()
                    ->map(function ($section) {
                        return [
                            'id' => $section->id,
                            'name' => $section->name,
                            'description' => $section->description,
                            'status' => $section->status,
                            'school' => $section->school?->name,
                        ];
                    });
            });

            return Inertia::render('Academic/Section', [
                'sections' => $schoolSections,
                'statusOptions' => ['active', 'inactive'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch school sections: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load school sections.');
        }
    }

    /**
     * Store a newly created school section.
     *
     * Creates a section for the active school with validated data.
     *
     * @param StoreSchoolSectionRequest $request The validated request.
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Exception If section creation fails.
     */
    public function store(StoreSchoolSectionRequest $request)
    {
        try {
            $school = GetSchoolModel();
            if (!$school) {
                abort(403, 'No active school found.');
            }

            $validated = $request->validated();
            $validated['school_id'] = $school->id;

            SchoolSection::create($validated);

            Cache::forget("sections.school_{$school->id}");

            return redirect()
                ->route('sections.index')
                ->with('success', 'School section created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create school section: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to create school section.');
        }
    }

    /**
     * Display a specific school section.
     *
     * Shows details of a specific section.
     *
     * @param SchoolSection $schoolSection The school section to display.
     * @return \Inertia\Response
     * @throws \Exception If section retrieval fails.
     */
    public function show(SchoolSection $schoolSection)
    {
        try {
            permitted('view-sections');

            $school = GetSchoolModel();
            if (!$school || $schoolSection->school_id !== $school->id) {
                abort(403, 'Unauthorized access to school section.');
            }

            return Inertia::render('Academic/Section/Show', [
                'section' => [
                    'id' => $schoolSection->id,
                    'name' => $schoolSection->name,
                    'description' => $schoolSection->description,
                    'status' => $schoolSection->status,
                    'school' => $schoolSection->school?->name,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to show school section: ' . $e->getMessage());
            return redirect()->route('sections.index')->with('error', 'Failed to load school section.');
        }
    }

    /**
     * Update an existing school section.
     *
     * Updates a section with validated data, ensuring it belongs to the active school.
     *
     * @param UpdateSchoolSectionRequest $request The validated request.
     * @param SchoolSection $schoolSection The section to update.
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Exception If section update fails.
     */
    public function update(UpdateSchoolSectionRequest $request, SchoolSection $schoolSection)
    {
        try {
            permitted('manage-sections');

            $school = GetSchoolModel();
            if (!$school || $schoolSection->school_id !== $school->id) {
                abort(403, 'Unauthorized access to school section.');
            }

            $schoolSection->update($request->validated());

            Cache::forget("sections.school_{$school->id}");

            return redirect()
                ->route('sections.index')
                ->with('success', 'School section updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update school section: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update school section.');
        }
    }

    /**
     * Delete one or more school sections.
     *
     * Supports bulk deletion by IDs for the active school.
     *
     * @param Request $request The incoming HTTP request.
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception If section deletion fails.
     */
    public function destroy(Request $request)
    {
        try {
            permitted('manage-sections');

            $school = GetSchoolModel();
            if (!$school) {
                abort(403, 'No active school found.');
            }

            $ids = $request->input('ids', []);

            if (!empty($ids)) {
                SchoolSection::where('school_id', $school->id)
                    ->whereIn('id', $ids)
                    ->delete();

                Cache::forget("sections.school_{$school->id}");

                return response()->json(['success' => true, 'message' => 'School sections deleted successfully.']);
            }

            return response()->json(['success' => false, 'error' => 'No valid section IDs provided.'], 400);
        } catch (\Exception $e) {
            Log::error('Failed to delete school sections: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to delete school sections.'], 500);
        }
    }

    /**
     * Retrieve paginated school sections for dropdown options.
     *
     * Returns sections for the active school in JSON format.
     *
     * @param Request $request The incoming HTTP request.
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception If section retrieval fails.
     */
    public function options(Request $request)
    {
        try {
            if ($request->isJson()) {
                $school = GetSchoolModel();
                if (!$school) {
                    abort(403, 'No active school found.');
                }

                return SchoolSection::where('school_id', $school->id)
                    ->select(['id', 'name'])
                    ->paginate($request->input('per_page', 10));
            }

            return response()->json(['error' => 'Invalid request format.'], 400);
        } catch (\Exception $e) {
            Log::error('Failed to fetch section options: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch section options.'], 500);
        }
    }
}
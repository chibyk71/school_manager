<?php

namespace App\Http\Controllers;

use App\Models\Resource\Assignment;
use App\Models\Academic\ClassLevel;
use App\Models\Academic\Subject;
use App\Models\Academic\Term;
use App\Models\Employee\Staff;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Controller for managing assignments in the school management system.
 *
 * Handles CRUD operations, including soft delete, force delete, and restore,
 * for assignments, ensuring proper authorization, validation, and school scoping
 * for a multi-tenant SaaS environment.
 *
 * @package App\Http\Controllers
 * 
 * TODO use policies instead of permisions
 */
class AssignmentController extends Controller
{
    /**
     * Display a listing of assignments with search, filter, sort, and pagination.
     *
     * Uses the HasTableQuery trait to handle dynamic querying.
     * Renders the Academic/Homework Vue component or returns JSON for API requests.
     *
     * @param Request $request The HTTP request containing query parameters.
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     * @throws \Exception If query fails or no active school is found.
     */
    public function index(Request $request)
    {
        // Check permission for viewing assignments
        permitted('assignments.view', $request->wantsJson());

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Define extra fields for table query
            $extraFields = [
                'teacher' => ['field' => 'teacher.name', 'filterable' => true, 'sortable' => true, 'filterType' => 'text'],
                'classLevel' => ['field' => 'class_level.name', 'filterable' => true, 'sortable' => true, 'filterType' => 'text'],
                'subject' => ['field' => 'subject.name', 'filterable' => true, 'sortable' => true, 'filterType' => 'text'],
                'term' => ['field' => 'term.name', 'filterable' => true, 'sortable' => true, 'filterType' => 'text'],
            ];

            // Build query
            $query = Assignment::with([
                'teacher:id,first_name,last_name',
                'classLevel:id,name',
                'subject:id,name',
                'term:id,name',
            ])->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Apply dynamic table query
            $assignments = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($assignments);
            }

            return Inertia::render('Academic/Homework', [
                'assignments' => $assignments,
                'filters' => $request->only(['search', 'sort', 'sortOrder', 'perPage', 'with_trashed']),
                'classLevels' => ClassLevel::where('school_id', $school->id)->select('id', 'name')->get(),
                'subjects' => Subject::where('school_id', $school->id)->select('id', 'name')->get(),
                'terms' => Term::where('school_id', $school->id)->select('id', 'name')->get(),
                'teachers' => Staff::where('school_id', $school->id)
                    ->whereHas('roles', fn($query) => $query->where('name', 'teacher'))
                    ->select('id', 'first_name', 'last_name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch assignments: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch assignments'], 500)
                : redirect()->back()->with('error', 'Failed to load assignments. Please try again.');
        }
    }

    /**
     * Show the form for creating a new assignment.
     *
     * Fetches related data (class levels, subjects, terms, teachers) for the form.
     * Renders the Academic/AssignmentCreate Vue component.
     *
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function create()
    {
        // Check permission for creating assignments
        permitted('assignments.create');

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Fetch related data for the form, scoped to the school
            $classLevels = ClassLevel::where('school_id', $school->id)->select('id', 'name')->get();
            $subjects = Subject::where('school_id', $school->id)->select('id', 'name')->get();
            $terms = Term::where('school_id', $school->id)->select('id', 'name')->get();
            $teachers = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->where('name', 'teacher'))
                ->select('id', 'first_name', 'last_name')->get();

            // Return Inertia response with form data
            return Inertia::render('Academic/AssignmentCreate', [
                'classLevels' => $classLevels,
                'subjects' => $subjects,
                'terms' => $terms,
                'teachers' => $teachers,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load assignment creation form: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load assignment creation form.');
        }
    }

    /**
     * Store a newly created assignment in storage.
     *
     * Validates the input and creates the assignment with associated media if provided.
     *
     * @param Request $request The HTTP request containing assignment data.
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If creation fails.
     */
    public function store(Request $request)
    {
        // Check permission for creating assignments
        permitted('create-assignments', $request->wantsJson());

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'class_level_id' => 'required|exists:class_levels,id,school_id,' . $school->id,
                'subject_id' => 'required|exists:subjects,id,school_id,' . $school->id,
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'term_id' => 'required|exists:terms,id,school_id,' . $school->id,
                'total_mark' => 'required|integer|min:1',
                'due_date' => 'required|date|after:now',
                'teacher_id' => 'required|exists:staff,id,school_id,' . $school->id,
                'media' => 'nullable|array',
                'media.*' => 'file|mimes:pdf,doc,docx,jpg,png|max:2048',
            ])->validate();

            // Create the assignment
            $assignment = Assignment::create([
                'school_id' => $school->id,
                'class_level_id' => $validated['class_level_id'],
                'subject_id' => $validated['subject_id'],
                'title' => $validated['title'],
                'description' => $validated['description'],
                'term_id' => $validated['term_id'],
                'total_mark' => $validated['total_mark'],
                'due_date' => $validated['due_date'],
                'teacher_id' => $validated['teacher_id'],
            ]);

            // Attach media if provided
            if ($request->hasFile('media')) {
                foreach ($request->file('media') as $file) {
                    $assignment->addMedia($file)->toMediaCollection('assignments');
                }
            }

            return $request->wantsJson()
                ? response()->json(['message' => 'Assignment created successfully'], 201)
                : redirect()->route('assignments.index')->with('success', 'Assignment created successfully.');
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to create assignment: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create assignment'], 500)
                : redirect()->back()->with('error', 'Failed to create assignment. Please try again.');
        }
    }

    /**
     * Display the specified assignment.
     *
     * Loads the assignment with related data and returns a JSON response.
     *
     * @param Request $request The HTTP request.
     * @param Assignment $assignment The assignment to display.
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception If no active school is found or assignment is not accessible.
     */
    public function show(Request $request, Assignment $assignment)
    {
        // Check permission for viewing assignments
        permitted('assignments.view', true);

        try {
            $school = GetSchoolModel();
            if (!$school || $assignment->school_id !== $school->id) {
                throw new \Exception('Assignment not found or not accessible.');
            }

            // Load related data
            $assignment->load(['teacher', 'classLevel', 'subject', 'term', 'media']);

            return response()->json(['assignment' => $assignment]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch assignment: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch assignment'], 500);
        }
    }

    /**
     * Show the form for editing the specified assignment.
     *
     * Fetches related data and renders the Academic/AssignmentEdit Vue component.
     *
     * @param Assignment $assignment The assignment to edit.
     * @return \Inertia\Response
     * @throws \Exception If no active school is found or assignment is not accessible.
     */
    public function edit(Assignment $assignment)
    {
        // Check permission for editing assignments
        permitted('assignments.update', true);

        try {
            $school = GetSchoolModel();
            if (!$school || $assignment->school_id !== $school->id) {
                throw new \Exception('Assignment not found or not accessible.');
            }

            // Load related data
            $assignment->load(['teacher', 'classLevel', 'subject', 'term', 'media']);

            // Fetch related data for the form
            $classLevels = ClassLevel::where('school_id', $school->id)->select('id', 'name')->get();
            $subjects = Subject::where('school_id', $school->id)->select('id', 'name')->get();
            $terms = Term::where('school_id', $school->id)->select('id', 'name')->get();
            $teachers = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->where('name', 'teacher'))
                ->select('id', 'first_name', 'last_name')->get();

            return Inertia::render('Academic/AssignmentEdit', [
                'assignment' => $assignment,
                'classLevels' => $classLevels,
                'subjects' => $subjects,
                'terms' => $terms,
                'teachers' => $teachers,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load assignment edit form: ' . $e->getMessage());
            return redirect()->route('assignments.index')->with('error', 'Failed to load assignment edit form.');
        }
    }

    /**
     * Update the specified assignment in storage.
     *
     * Validates the input and updates the assignment with associated media if provided.
     *
     * @param Request $request The HTTP request containing updated assignment data.
     * @param Assignment $assignment The assignment to update.
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If update fails.
     */
    public function update(Request $request, Assignment $assignment)
    {
        // Check permission for editing assignments
        permitted('assignments.update', $request->wantsJson());

        try {
            $school = GetSchoolModel();
            if (!$school || $assignment->school_id !== $school->id) {
                throw new \Exception('Assignment not found or not accessible.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'class_level_id' => 'required|exists:class_levels,id,school_id,' . $school->id,
                'subject_id' => 'required|exists:subjects,id,school_id,' . $school->id,
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'term_id' => 'required|exists:terms,id,school_id,' . $school->id,
                'total_mark' => 'required|integer|min:1',
                'due_date' => 'required|date|after:now',
                'teacher_id' => 'required|exists:staff,id,school_id,' . $school->id,
                'media' => 'nullable|array',
                'media.*' => 'file|mimes:pdf,doc,docx,jpg,png|max:2048',
            ])->validate();

            // Update the assignment
            $assignment->update([
                'class_level_id' => $validated['class_level_id'],
                'subject_id' => $validated['subject_id'],
                'title' => $validated['title'],
                'description' => $validated['description'],
                'term_id' => $validated['term_id'],
                'total_mark' => $validated['total_mark'],
                'due_date' => $validated['due_date'],
                'teacher_id' => $validated['teacher_id'],
            ]);

            // Sync media if provided
            if ($request->hasFile('media')) {
                $assignment->clearMediaCollection('assignments');
                foreach ($request->file('media') as $file) {
                    $assignment->addMedia($file)->toMediaCollection('assignments');
                }
            }

            return $request->wantsJson()
                ? response()->json(['message' => 'Assignment updated successfully'])
                : redirect()->route('assignments.index')->with('success', 'Assignment updated successfully.');
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to update assignment: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update assignment'], 500)
                : redirect()->back()->with('error', 'Failed to update assignment. Please try again.');
        }
    }

    /**
     * Remove one or more assignments from storage (soft or force delete).
     *
     * Accepts an array of assignment IDs via JSON request and performs soft or force delete
     * based on the 'force' parameter.
     *
     * @param Request $request The HTTP request containing an array of assignment IDs.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If deletion fails or assignments are not accessible.
     */
    public function destroy(Request $request)
    {
        // Check permission for deleting assignments
        permitted('assignments.delete', $request->wantsJson());

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'exists:assignments,id,school_id,' . $school->id,
                'force' => 'sometimes|boolean',
            ])->validate();

            // Perform soft or force delete
            $forceDelete = $request->boolean('force');
            $deleted = $forceDelete
                ? Assignment::whereIn('id', $validated['ids'])->where('school_id', $school->id)->forceDelete()
                : Assignment::whereIn('id', $validated['ids'])->where('school_id', $school->id)->delete();

            $message = $deleted ? "$deleted assignment(s) deleted successfully" : "No assignments were deleted";

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('assignments.index')->with('success', $message);
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to delete assignments: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete assignment(s)'], 500)
                : redirect()->back()->with('error', 'Failed to delete assignment(s).');
        }
    }

    /**
     * Restore one or more soft-deleted assignments.
     *
     * Accepts an array of assignment IDs via JSON request and restores them.
     *
     * @param Request $request The HTTP request containing an array of assignment IDs.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If restoration fails or assignments are not accessible.
     */
    public function restore(Request $request)
    {
        // Check permission for restoring assignments
        permitted('assignments.restore', $request->wantsJson());

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'exists:assignments,id,school_id,' . $school->id,
            ])->validate();

            // Restore the assignments
            $count = Assignment::onlyTrashed()
                ->whereIn('id', $validated['ids'])
                ->where('school_id', $school->id)
                ->restore();

            $message = $count ? "$count assignment(s) restored successfully" : "No assignments were restored";

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('assignments.index')->with('success', $message);
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to restore assignments: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to restore assignment(s)'], 500)
                : redirect()->back()->with('error', 'Failed to restore assignment(s).');
        }
    }
}
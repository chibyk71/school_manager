<?php

namespace App\Http\Controllers;

use App\Models\Resource\Assignment;
use App\Models\Resource\AssignmentSubmission;
use App\Models\People\Student;
use App\Models\Employee\Staff;
use App\Notifications\AssignmentSubmissionCreated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Controller for managing assignment submissions in the school management system.
 *
 * Handles CRUD operations, including soft delete, force delete, and restore,
 * for assignment submissions, ensuring proper authorization, validation, and school scoping
 * for a multi-tenant SaaS environment.
 *
 * @package App\Http\Controllers
 */
class AssignmentSubmissionController extends Controller
{
    /**
     * Display a listing of assignment submissions with search, filter, sort, and pagination.
     *
     * Uses the HasTableQuery trait to handle dynamic querying.
     * Renders the Academic/AssignmentSubmissions Vue component or returns JSON for API requests.
     *
     * @param Request $request The HTTP request containing query parameters.
     * @param Assignment|null $assignment Optional assignment to filter submissions by.
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     * @throws \Exception If query fails or no active school is found.
     */
    public function index(Request $request, ?Assignment $assignment = null)
    {
        Gate::authorize('viewAny', AssignmentSubmission::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Define extra fields for table query
            $extraFields = [
                'student' => ['field' => 'student.full_name', 'filterable' => true, 'sortable' => true, 'filterType' => 'text'],
                'assignment' => ['field' => 'assignment.title', 'filterable' => true, 'sortable' => true, 'filterType' => 'text'],
                'gradedBy' => ['field' => 'gradedBy.full_name', 'filterable' => true, 'sortable' => true, 'filterType' => 'text'],
            ];

            // Build query
            $query = AssignmentSubmission::with([
                'student:id,first_name,last_name',
                'assignment:id,title',
                'gradedBy:id,first_name,last_name',
            ])->when($assignment, fn($q) => $q->where('assignment_id', $assignment->id))
              ->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Apply dynamic table query
            $submissions = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($submissions);
            }

            return Inertia::render('Academic/AssignmentSubmissions', [
                'assignment' => $assignment ? $assignment->only('id', 'title') : null,
                'submissions' => $submissions,
                'filters' => $request->only(['search', 'sort', 'sortOrder', 'perPage', 'with_trashed']),
                'students' => Student::where('school_id', $school->id)->select('id', 'first_name', 'last_name')->get(),
                'assignments' => Assignment::where('school_id', $school->id)->select('id', 'title')->get(),
                'teachers' => Staff::where('school_id', $school->id)
                    ->whereHas('roles', fn($query) => $query->where('name', 'teacher'))
                    ->select('id', 'first_name', 'last_name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch assignment submissions: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch assignment submissions'], 500)
                : redirect()->back()->with('error', 'Failed to load assignment submissions.');
        }
    }

    /**
     * Show the form for creating a new assignment submission.
     *
     * Renders the Academic/AssignmentSubmissionCreate Vue component.
     *
     * @param Request $request
     * @param Assignment|null $assignment Optional assignment for pre-selection.
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function create(Request $request, ?Assignment $assignment = null)
    {
        Gate::authorize('create', AssignmentSubmission::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            return Inertia::render('Academic/AssignmentSubmissionCreate', [
                'assignment' => $assignment ? $assignment->only('id', 'title') : null,
                'students' => Student::where('school_id', $school->id)->select('id', 'first_name', 'last_name')->get(),
                'assignments' => Assignment::where('school_id', $school->id)->select('id', 'title')->get(),
                'teachers' => Staff::where('school_id', $school->id)
                    ->whereHas('roles', fn($query) => $query->where('name', 'teacher'))
                    ->select('id', 'first_name', 'last_name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load assignment submission create form: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load create form.');
        }
    }

    /**
     * Store a newly created assignment submission in storage.
     *
     * Validates the input, creates the submission with associated media, and sends notifications.
     *
     * @param Request $request The HTTP request containing submission data.
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If creation fails.
     */
    public function store(Request $request)
    {
        Gate::authorize('create', AssignmentSubmission::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'student_id' => 'required|exists:students,id,school_id,' . $school->id,
                'assignment_id' => 'required|exists:assignments,id,school_id,' . $school->id,
                'answer_text' => 'nullable|string',
                'mark_obtained' => 'nullable|numeric|min:0',
                'status' => 'required|in:draft,submitted,graded',
                'submitted_at' => 'required|date',
                'graded_by' => 'nullable|exists:staff,id,school_id,' . $school->id,
                'remark' => 'nullable|string',
                'media' => 'nullable|array',
                'media.*' => 'file|mimes:pdf,doc,docx,jpg,png|max:2048',
            ])->validate();

            // Create the submission
            $submission = AssignmentSubmission::create([
                'school_id' => $school->id,
                'student_id' => $validated['student_id'],
                'assignment_id' => $validated['assignment_id'],
                'answer_text' => $validated['answer_text'],
                'mark_obtained' => $validated['mark_obtained'],
                'status' => $validated['status'],
                'submitted_at' => $validated['submitted_at'],
                'graded_by' => $validated['graded_by'],
                'remark' => $validated['remark'],
            ]);

            // Attach media if provided
            if ($request->hasFile('media')) {
                foreach ($request->file('media') as $file) {
                    $submission->addMedia($file)->toMediaCollection('submissions');
                }
            }

            // Notify student and teacher (if applicable)
            $submission->student->notify(new AssignmentSubmissionCreated($submission, 'created'));
            if ($submission->assignment->teacher) {
                $submission->assignment->teacher->notify(new AssignmentSubmissionCreated($submission, 'created'));
            }

            return $request->wantsJson()
                ? response()->json(['message' => 'Assignment submission created successfully'], 201)
                : redirect()->route('assignment-submissions.index')->with('success', 'Assignment submission created successfully.');
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to create assignment submission: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create assignment submission'], 500)
                : redirect()->back()->with('error', 'Failed to create assignment submission.');
        }
    }

    /**
     * Display the specified assignment submission.
     *
     * Loads the submission with related data and returns a JSON response.
     *
     * @param Request $request The HTTP request.
     * @param AssignmentSubmission $assignmentSubmission The submission to display.
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception If no active school is found or submission is not accessible.
     */
    public function show(Request $request, AssignmentSubmission $assignmentSubmission)
    {
        Gate::authorize('view', $assignmentSubmission);

        try {
            $school = GetSchoolModel();
            if (!$school || $assignmentSubmission->assignment->school_id !== $school->id) {
                throw new \Exception('Assignment submission not found or not accessible.');
            }

            $assignmentSubmission->load(['student', 'assignment', 'gradedBy', 'media']);

            return response()->json(['assignment_submission' => $assignmentSubmission]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch assignment submission: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch assignment submission'], 500);
        }
    }

    /**
     * Show the form for editing the specified assignment submission.
     *
     * Renders the Academic/AssignmentSubmissionEdit Vue component.
     *
     * @param AssignmentSubmission $assignmentSubmission The submission to edit.
     * @return \Inertia\Response
     * @throws \Exception If no active school is found or submission is not accessible.
     */
    public function edit(AssignmentSubmission $assignmentSubmission)
    {
        Gate::authorize('update', $assignmentSubmission);

        try {
            $school = GetSchoolModel();
            if (!$school || $assignmentSubmission->assignment->school_id !== $school->id) {
                throw new \Exception('Assignment submission not found or not accessible.');
            }

            $assignmentSubmission->load(['student', 'assignment', 'gradedBy', 'media']);

            return Inertia::render('Academic/AssignmentSubmissionEdit', [
                'assignmentSubmission' => $assignmentSubmission,
                'students' => Student::where('school_id', $school->id)->select('id', 'first_name', 'last_name')->get(),
                'assignments' => Assignment::where('school_id', $school->id)->select('id', 'title')->get(),
                'teachers' => Staff::where('school_id', $school->id)
                    ->whereHas('roles', fn($query) => $query->where('name', 'teacher'))
                    ->select('id', 'first_name', 'last_name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load assignment submission edit form: ' . $e->getMessage());
            return redirect()->route('assignment-submissions.index')->with('error', 'Failed to load edit form.');
        }
    }

    /**
     * Update the specified assignment submission in storage.
     *
     * Validates the input, updates the submission with associated media, and sends notifications.
     *
     * @param Request $request The HTTP request containing updated submission data.
     * @param AssignmentSubmission $assignmentSubmission The submission to update.
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If update fails.
     */
    public function update(Request $request, AssignmentSubmission $assignmentSubmission)
    {
        Gate::authorize('update', $assignmentSubmission);

        try {
            $school = GetSchoolModel();
            if (!$school || $assignmentSubmission->assignment->school_id !== $school->id) {
                throw new \Exception('Assignment submission not found or not accessible.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'student_id' => 'required|exists:students,id,school_id,' . $school->id,
                'assignment_id' => 'required|exists:assignments,id,school_id,' . $school->id,
                'answer_text' => 'nullable|string',
                'mark_obtained' => 'nullable|numeric|min:0',
                'status' => 'required|in:draft,submitted,graded',
                'submitted_at' => 'required|date',
                'graded_by' => 'nullable|exists:staff,id,school_id,' . $school->id,
                'remark' => 'nullable|string',
                'media' => 'nullable|array',
                'media.*' => 'file|mimes:pdf,doc,docx,jpg,png|max:2048',
            ])->validate();

            // Update the submission
            $assignmentSubmission->update([
                'student_id' => $validated['student_id'],
                'assignment_id' => $validated['assignment_id'],
                'answer_text' => $validated['answer_text'],
                'mark_obtained' => $validated['mark_obtained'],
                'status' => $validated['status'],
                'submitted_at' => $validated['submitted_at'],
                'graded_by' => $validated['graded_by'],
                'remark' => $validated['remark'],
            ]);

            // Sync media if provided
            if ($request->hasFile('media')) {
                $assignmentSubmission->clearMediaCollection('submissions');
                foreach ($request->file('media') as $file) {
                    $assignmentSubmission->addMedia($file)->toMediaCollection('submissions');
                }
            }

            // Notify student and teacher (if applicable)
            $assignmentSubmission->student->notify(new AssignmentSubmissionCreated($assignmentSubmission, 'updated'));
            if ($assignmentSubmission->assignment->teacher) {
                $assignmentSubmission->assignment->teacher->notify(new AssignmentSubmissionCreated($assignmentSubmission, 'updated'));
            }

            return $request->wantsJson()
                ? response()->json(['message' => 'Assignment submission updated successfully'])
                : redirect()->route('assignment-submissions.index')->with('success', 'Assignment submission updated successfully.');
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to update assignment submission: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update assignment submission'], 500)
                : redirect()->back()->with('error', 'Failed to update assignment submission.');
        }
    }

    /**
     * Remove one or more assignment submissions from storage (soft or force delete).
     *
     * Accepts an array of submission IDs via JSON request, performs soft or force delete,
     * and sends notifications.
     *
     * @param Request $request The HTTP request containing an array of submission IDs.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If deletion fails or submissions are not accessible.
     */
    public function destroy(Request $request)
    {
        Gate::authorize('delete', AssignmentSubmission::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'exists:assignment_submissions,id',
                'force' => 'sometimes|boolean',
            ])->validate();

            // Notify before deletion
            $submissions = AssignmentSubmission::whereIn('id', $validated['ids'])
                ->whereHas('assignment', fn($q) => $q->where('school_id', $school->id))
                ->get();
            foreach ($submissions as $submission) {
                $submission->student->notify(new AssignmentSubmissionCreated($submission, 'deleted'));
                if ($submission->assignment->teacher) {
                    $submission->assignment->teacher->notify(new AssignmentSubmissionCreated($submission, 'deleted'));
                }
            }

            // Perform soft or force delete
            $forceDelete = $request->boolean('force');
            $query = AssignmentSubmission::whereIn('id', $validated['ids'])
                ->whereHas('assignment', fn($q) => $q->where('school_id', $school->id));
            $deleted = $forceDelete ? $query->forceDelete() : $query->delete();

            $message = $deleted ? "$deleted assignment submission(s) deleted successfully" : "No assignment submissions were deleted";

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('assignment-submissions.index')->with('success', $message);
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to delete assignment submissions: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete assignment submission(s)'], 500)
                : redirect()->back()->with('error', 'Failed to delete assignment submission(s).');
        }
    }

    /**
     * Restore one or more soft-deleted assignment submissions.
     *
     * Accepts an array of submission IDs via JSON request, restores them, and sends notifications.
     *
     * @param Request $request The HTTP request containing an array of submission IDs.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If restoration fails or submissions are not accessible.
     */
    public function restore(Request $request)
    {
        Gate::authorize('restore', AssignmentSubmission::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'exists:assignment_submissions,id',
            ])->validate();

            // Notify before restoration
            $submissions = AssignmentSubmission::onlyTrashed()
                ->whereIn('id', $validated['ids'])
                ->whereHas('assignment', fn($q) => $q->where('school_id', $school->id))
                ->get();
            foreach ($submissions as $submission) {
                $submission->student->notify(new AssignmentSubmissionCreated($submission, 'restored'));
                if ($submission->assignment->teacher) {
                    $submission->assignment->teacher->notify(new AssignmentSubmissionCreated($submission, 'restored'));
                }
            }

            // Restore the submissions
            $count = AssignmentSubmission::onlyTrashed()
                ->whereIn('id', $validated['ids'])
                ->whereHas('assignment', fn($q) => $q->where('school_id', $school->id))
                ->restore();

            $message = $count ? "$count assignment submission(s) restored successfully" : "No assignment submissions were restored";

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('assignment-submissions.index')->with('success', $message);
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to restore assignment submissions: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to restore assignment submission(s)'], 500)
                : redirect()->back()->with('error', 'Failed to restore assignment submission(s).');
        }
    }
}
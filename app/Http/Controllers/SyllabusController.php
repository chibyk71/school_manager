<?php

namespace App\Http\Controllers;

use App\Models\Academic\ClassLevel;
use App\Models\Academic\Subject;
use App\Models\Academic\Term;
use App\Models\Resource\Syllabus;
use App\Models\Resource\SyllabusApproval;
use App\Models\Employee\Staff;
use App\Notifications\SyllabusAction;
use App\Notifications\SyllabusApprovalAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Controller for managing syllabi in the school management system.
 *
 * Handles CRUD operations, including soft delete, force delete, restore, and approval workflows,
 * for syllabi, ensuring proper authorization, validation, school scoping,
 * and notifications for a multi-tenant SaaS environment.
 *
 * @package App\Http\Controllers
 */
class SyllabusController extends Controller
{
    /**
     * Display a listing of syllabi with search, filter, sort, and pagination.
     *
     * Uses the HasTableQuery trait to handle dynamic querying.
     * Renders the Academic/Syllabi Vue component or returns JSON for API requests.
     *
     * @param Request $request The HTTP request containing query parameters.
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     * @throws \Exception If query fails or no active school is found.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Syllabus::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Define extra fields for table query
            $extraFields = [
                'classLevel' => ['field' => 'class_level.name', 'filterable' => true, 'sortable' => true, 'filterType' => 'text'],
                'subject' => ['field' => 'subject.name', 'filterable' => true, 'sortable' => true, 'filterType' => 'text'],
                'term' => ['field' => 'term.name', 'filterable' => true, 'sortable' => true, 'filterType' => 'text'],
            ];

            // Build query
            $query = Syllabus::with([
                'classLevel:id,name',
                'subject:id,name',
                'term:id,name',
                'latestApproval' => function ($query) {
                    $query->select('id', 'syllabus_id', 'status', 'comments');
                },
            ])->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Apply dynamic table query
            $syllabi = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($syllabi);
            }

            return Inertia::render('Academic/Syllabi', [
                'syllabi' => $syllabi,
                'filters' => $request->only(['search', 'sort', 'sortOrder', 'perPage', 'with_trashed']),
                'classLevels' => ClassLevel::where('school_id', $school->id)->select('id', 'name')->get(),
                'subjects' => Subject::where('school_id', $school->id)->select('id', 'name')->get(),
                'terms' => Term::where('school_id', $school->id)->select('id', 'name')->get(),
                'approvers' => Staff::where('school_id', $school->id)
                    ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'head_teacher']))
                    ->select('id', 'first_name', 'last_name')
                    ->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch syllabi: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch syllabi'], 500)
                : redirect()->back()->with('error', 'Failed to load syllabi.');
        }
    }

    /**
     * Show the form for creating a new syllabus.
     *
     * Renders the Academic/SyllabusCreate Vue component.
     *
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function create()
    {
        Gate::authorize('create', Syllabus::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            return Inertia::render('Academic/SyllabusCreate', [
                'classLevels' => ClassLevel::where('school_id', $school->id)->select('id', 'name')->get(),
                'subjects' => Subject::where('school_id', $school->id)->select('id', 'name')->get(),
                'terms' => Term::where('school_id', $school->id)->select('id', 'name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load syllabus creation form: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load syllabus creation form.');
        }
    }

    /**
     * Store a newly created syllabus in storage.
     *
     * Validates the input, creates the syllabus, attaches media, and sends notifications.
     *
     * @param Request $request The HTTP request containing syllabus data.
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If creation fails.
     */
    public function store(Request $request)
    {
        Gate::authorize('create', Syllabus::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'class_level_id' => 'required|exists:class_levels,id,school_id,' . $school->id,
                'subject_id' => 'required|exists:subjects,id,school_id,' . $school->id,
                'term_id' => 'required|exists:terms,id,school_id,' . $school->id,
                'topic' => 'required|string|max:255',
                'sub_topic' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'status' => 'required|in:draft,pending_approval,published,rejected,archived',
                'options' => 'nullable|array',
                'media' => 'nullable|array',
                'media.*' => 'file|mimes:pdf,jpg,png|max:2048',
            ])->validate();

            // Create the syllabus
            $syllabus = Syllabus::create([
                'school_id' => $school->id,
                'class_level_id' => $validated['class_level_id'],
                'subject_id' => $validated['subject_id'],
                'term_id' => $validated['term_id'],
                'topic' => $validated['topic'],
                'sub_topic' => $validated['sub_topic'],
                'description' => $validated['description'],
                'status' => $validated['status'],
                'options' => $validated['options'],
            ]);

            // Attach media if provided
            if ($request->hasFile('media')) {
                foreach ($request->file('media') as $file) {
                    $syllabus->addMedia($file)->toMediaCollection('syllabus_files');
                }
            }

            // Notify staff
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            Notification::send($users, new SyllabusAction($syllabus, 'created'));

            // If status is pending_approval, create an approval request
            if ($syllabus->status === 'pending_approval') {
                $approval = SyllabusApproval::create([
                    'school_id' => $school->id,
                    'syllabus_id' => $syllabus->id,
                    'requester_id' => Auth::user()->staff->id,
                    'status' => 'pending',
                ]);
                Notification::send($users, new SyllabusApprovalAction($syllabus, $approval, 'submitted'));
            }

            return $request->wantsJson()
                ? response()->json(['message' => 'Syllabus created successfully'], 201)
                : redirect()->route('syllabi.index')->with('success', 'Syllabus created successfully.');
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to create syllabus: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create syllabus'], 500)
                : redirect()->back()->with('error', 'Failed to create syllabus.');
        }
    }

    /**
     * Display the specified syllabus.
     *
     * Loads the syllabus with related data and returns a JSON response.
     *
     * @param Request $request The HTTP request.
     * @param Syllabus $syllabus The syllabus to display.
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception If no active school is found or syllabus is not accessible.
     */
    public function show(Request $request, Syllabus $syllabus)
    {
        Gate::authorize('view', $syllabus);

        try {
            $school = GetSchoolModel();
            if (!$school || $syllabus->school_id !== $school->id) {
                throw new \Exception('Syllabus not found or not accessible.');
            }

            $syllabus->load([
                'classLevel',
                'subject',
                'term',
                'approvals' => function ($query) {
                    $query->with(['requester', 'approver'])->orderBy('created_at', 'desc');
                },
                'media',
            ]);

            return response()->json(['syllabus' => $syllabus]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch syllabus: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch syllabus'], 500);
        }
    }

    /**
     * Show the form for editing the specified syllabus.
     *
     * Renders the Academic/SyllabusEdit Vue component.
     *
     * @param Syllabus $syllabus The syllabus to edit.
     * @return \Inertia\Response
     * @throws \Exception If no active school is found or syllabus is not accessible.
     */
    public function edit(Syllabus $syllabus)
    {
        Gate::authorize('update', $syllabus);

        try {
            $school = GetSchoolModel();
            if (!$school || $syllabus->school_id !== $school->id) {
                throw new \Exception('Syllabus not found or not accessible.');
            }

            $syllabus->load(['classLevel', 'subject', 'term', 'approvals']);

            return Inertia::render('Academic/SyllabusEdit', [
                'syllabus' => $syllabus,
                'classLevels' => ClassLevel::where('school_id', $school->id)->select('id', 'name')->get(),
                'subjects' => Subject::where('school_id', $school->id)->select('id', 'name')->get(),
                'terms' => Term::where('school_id', $school->id)->select('id', 'name')->get(),
                'approvers' => Staff::where('school_id', $school->id)
                    ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'head_teacher']))
                    ->select('id', 'first_name', 'last_name')
                    ->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load syllabus edit form: ' . $e->getMessage());
            return redirect()->route('syllabi.index')->with('error', 'Failed to load syllabus edit form.');
        }
    }

    /**
     * Update the specified syllabus in storage.
     *
     * Validates the input, updates the syllabus, syncs media, and sends notifications.
     *
     * @param Request $request The HTTP request containing updated syllabus data.
     * @param Syllabus $syllabus The syllabus to update.
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If update fails.
     */
    public function update(Request $request, Syllabus $syllabus)
    {
        Gate::authorize('update', $syllabus);

        try {
            $school = GetSchoolModel();
            if (!$school || $syllabus->school_id !== $school->id) {
                throw new \Exception('Syllabus not found or not accessible.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'class_level_id' => 'required|exists:class_levels,id,school_id,' . $school->id,
                'subject_id' => 'required|exists:subjects,id,school_id,' . $school->id,
                'term_id' => 'required|exists:terms,id,school_id,' . $school->id,
                'topic' => 'required|string|max:255',
                'sub_topic' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'status' => 'required|in:draft,pending_approval,published,rejected,archived',
                'options' => 'nullable|array',
                'media' => 'nullable|array',
                'media.*' => 'file|mimes:pdf,jpg,png|max:2048',
            ])->validate();

            // Update the syllabus
            $syllabus->update([
                'class_level_id' => $validated['class_level_id'],
                'subject_id' => $validated['subject_id'],
                'term_id' => $validated['term_id'],
                'topic' => $validated['topic'],
                'sub_topic' => $validated['sub_topic'],
                'description' => $validated['description'],
                'status' => $validated['status'],
                'options' => $validated['options'],
            ]);

            // Sync media if provided
            if ($request->hasFile('media')) {
                $syllabus->clearMediaCollection('syllabus_files');
                foreach ($request->file('media') as $file) {
                    $syllabus->addMedia($file)->toMediaCollection('syllabus_files');
                }
            }

            // Notify staff
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            Notification::send($users, new SyllabusAction($syllabus, 'updated'));

            // If status is pending_approval, create an approval request
            if ($syllabus->status === 'pending_approval') {
                $approval = SyllabusApproval::create([
                    'school_id' => $school->id,
                    'syllabus_id' => $syllabus->id,
                    'requester_id' => Auth::user()->staff->id,
                    'status' => 'pending',
                ]);
                Notification::send($users, new SyllabusApprovalAction($syllabus, $approval, 'submitted'));
            }

            return $request->wantsJson()
                ? response()->json(['message' => 'Syllabus updated successfully'])
                : redirect()->route('syllabi.index')->with('success', 'Syllabus updated successfully.');
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to update syllabus: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update syllabus'], 500)
                : redirect()->back()->with('error', 'Failed to update syllabus.');
        }
    }

    /**
     * Remove one or more syllabi from storage (soft or force delete).
     *
     * Accepts an array of syllabus IDs via JSON request, performs soft or force delete,
     * and sends notifications.
     *
     * @param Request $request The HTTP request containing an array of syllabus IDs.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If deletion fails or syllabi are not accessible.
     */
    public function destroy(Request $request)
    {
        Gate::authorize('delete', Syllabus::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'exists:syllabi,id,school_id,' . $school->id,
                'force' => 'sometimes|boolean',
            ])->validate();

            // Notify before deletion
            $syllabi = Syllabus::whereIn('id', $validated['ids'])
                ->where('school_id', $school->id)
                ->get();
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            foreach ($syllabi as $syllabus) {
                Notification::send($users, new SyllabusAction($syllabus, 'deleted'));
            }

            // Perform soft or force delete
            $forceDelete = $request->boolean('force');
            $query = Syllabus::whereIn('id', $validated['ids'])->where('school_id', $school->id);
            $deleted = $forceDelete ? $query->forceDelete() : $query->delete();

            $message = $deleted ? "$deleted syllabus(s) deleted successfully" : "No syllabi were deleted";

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('syllabi.index')->with('success', $message);
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to delete syllabi: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete syllabus(s)'], 500)
                : redirect()->back()->with('error', 'Failed to delete syllabus(s).');
        }
    }

    /**
     * Restore one or more soft-deleted syllabi.
     *
     * Accepts an array of syllabus IDs via JSON request, restores them, and sends notifications.
     *
     * @param Request $request The HTTP request containing an array of syllabus IDs.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If restoration fails or syllabi are not accessible.
     */
    public function restore(Request $request)
    {
        Gate::authorize('restore', Syllabus::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'exists:syllabi,id,school_id,' . $school->id,
            ])->validate();

            // Notify before restoration
            $syllabi = Syllabus::onlyTrashed()
                ->whereIn('id', $validated['ids'])
                ->where('school_id', $school->id)
                ->get();
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            foreach ($syllabi as $syllabus) {
                Notification::send($users, new SyllabusAction($syllabus, 'restored'));
            }

            // Restore the syllabi
            $count = Syllabus::onlyTrashed()
                ->whereIn('id', $validated['ids'])
                ->where('school_id', $school->id)
                ->restore();

            $message = $count ? "$count syllabus(s) restored successfully" : "No syllabi were restored";

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('syllabi.index')->with('success', $message);
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to restore syllabi: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to restore syllabus(s)'], 500)
                : redirect()->back()->with('error', 'Failed to restore syllabus(s).');
        }
    }

    /**
     * Submit a syllabus for approval.
     *
     * Creates an approval request and updates the syllabus status to pending_approval.
     *
     * @param Request $request The HTTP request.
     * @param Syllabus $syllabus The syllabus to submit for approval.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If submission fails.
     */
    public function submitApproval(Request $request, Syllabus $syllabus)
    {
        Gate::authorize('submitApproval', $syllabus);

        try {
            $school = GetSchoolModel();
            if (!$school || $syllabus->school_id !== $school->id) {
                throw new \Exception('Syllabus not found or not accessible.');
            }

            // Validate that the syllabus can be submitted
            if (!in_array($syllabus->status, ['draft', 'rejected'])) {
                throw new ValidationException(Validator::make([], [
                    'status' => 'The syllabus must be in draft or rejected status to submit for approval.',
                ]));
            }

            // Update status to pending_approval
            $syllabus->update(['status' => 'pending_approval']);

            // Create approval request
            $approval = SyllabusApproval::create([
                'school_id' => $school->id,
                'syllabus_id' => $syllabus->id,
                'requester_id' => Auth::user()->staff->id,
                'status' => 'pending',
            ]);

            // Notify staff
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'head_teacher']))
                ->get();
            Notification::send($users, new SyllabusApprovalAction($syllabus, $approval, 'submitted'));

            return $request->wantsJson()
                ? response()->json(['message' => 'Syllabus submitted for approval successfully'])
                : redirect()->route('syllabi.index')->with('success', 'Syllabus submitted for approval successfully.');
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to submit syllabus for approval: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to submit syllabus for approval'], 500)
                : redirect()->back()->with('error', 'Failed to submit syllabus for approval.');
        }
    }

    /**
     * Approve a syllabus.
     *
     * Updates the approval request and sets the syllabus status to published.
     *
     * @param Request $request The HTTP request containing approval comments.
     * @param Syllabus $syllabus The syllabus to approve.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If approval fails.
     */
    public function approve(Request $request, Syllabus $syllabus)
    {
        Gate::authorize('approve', $syllabus);

        try {
            $school = GetSchoolModel();
            if (!$school || $syllabus->school_id !== $school->id) {
                throw new \Exception('Syllabus not found or not accessible.');
            }

            // Validate that the syllabus is pending approval
            if ($syllabus->status !== 'pending_approval') {
                throw new ValidationException(Validator::make([], [
                    'status' => 'The syllabus must be pending approval to be approved.',
                ]));
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'comments' => 'nullable|string',
            ])->validate();

            // Update the latest approval request
            $approval = $syllabus->latestApproval;
            if (!$approval || $approval->status !== 'pending') {
                throw new \Exception('No pending approval request found.');
            }

            $approval->update([
                'approver_id' => Auth::user()->staff->id,
                'status' => 'approved',
                'comments' => $validated['comments'],
            ]);

            // Update syllabus status
            $syllabus->update(['status' => 'published']);

            // Notify staff
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            Notification::send($users, new SyllabusApprovalAction($syllabus, $approval, 'approved'));

            return $request->wantsJson()
                ? response()->json(['message' => 'Syllabus approved successfully'])
                : redirect()->route('syllabi.index')->with('success', 'Syllabus approved successfully.');
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to approve syllabus: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to approve syllabus'], 500)
                : redirect()->back()->with('error', 'Failed to approve syllabus.');
        }
    }

    /**
     * Reject a syllabus.
     *
     * Updates the approval request and sets the syllabus status to rejected.
     *
     * @param Request $request The HTTP request containing rejection comments.
     * @param Syllabus $syllabus The syllabus to reject.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If rejection fails.
     */
    public function reject(Request $request, Syllabus $syllabus)
    {
        Gate::authorize('reject', $syllabus);

        try {
            $school = GetSchoolModel();
            if (!$school || $syllabus->school_id !== $school->id) {
                throw new \Exception('Syllabus not found or not accessible.');
            }

            // Validate that the syllabus is pending approval
            if ($syllabus->status !== 'pending_approval') {
                throw new ValidationException(Validator::make([], [
                    'status' => 'The syllabus must be pending approval to be rejected.',
                ]));
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'comments' => 'required|string',
            ])->validate();

            // Update the latest approval request
            $approval = $syllabus->latestApproval;
            if (!$approval || $approval->status !== 'pending') {
                throw new \Exception('No pending approval request found.');
            }

            $approval->update([
                'approver_id' => Auth::user()->staff->id,
                'status' => 'rejected',
                'comments' => $validated['comments'],
            ]);

            // Update syllabus status
            $syllabus->update(['status' => 'rejected']);

            // Notify staff
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            Notification::send($users, new SyllabusApprovalAction($syllabus, $approval, 'rejected'));

            return $request->wantsJson()
                ? response()->json(['message' => 'Syllabus rejected successfully'])
                : redirect()->route('syllabi.index')->with('success', 'Syllabus rejected successfully.');
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to reject syllabus: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to reject syllabus'], 500)
                : redirect()->back()->with('error', 'Failed to reject syllabus.');
        }
    }
}

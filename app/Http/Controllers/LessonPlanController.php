<?php

namespace App\Http\Controllers;

use App\Models\Resource\LessonPlan;
use App\Models\Academic\ClassLevel;
use App\Models\Academic\Subject;
use App\Models\Academic\SylabusDetail;
use App\Models\Employee\Staff;
use App\Notifications\LessonPlanAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Controller for managing lesson plans in the school management system.
 *
 * Handles CRUD operations, including soft delete, force delete, and restore,
 * for lesson plans, ensuring proper authorization, validation, school scoping,
 * and notifications for a multi-tenant SaaS environment.
 *
 * @package App\Http\Controllers
 */
class LessonPlanController extends Controller
{
    /**
     * Display a listing of lesson plans with search, filter, sort, and pagination.
     *
     * Uses the HasTableQuery trait to handle dynamic querying.
     * Renders the Academic/LessonPlans Vue component or returns JSON for API requests.
     *
     * @param Request $request The HTTP request containing query parameters.
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     * @throws \Exception If query fails or no active school is found.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', LessonPlan::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Define extra fields for table query
            $extraFields = [
                'classLevel' => ['field' => 'class_level.name', 'filterable' => true, 'sortable' => true, 'filterType' => 'text'],
                'subject' => ['field' => 'subject.name', 'filterable' => true, 'sortable' => true, 'filterType' => 'text'],
                'staff' => ['field' => 'staff.full_name', 'filterable' => true, 'sortable' => true, 'filterType' => 'text'],
            ];

            // Build query
            $query = LessonPlan::with([
                'classLevel:id,name',
                'subject:id,name',
                'staff:id,first_name,last_name',
                'sylabusDetail:id,title',
            ])->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Apply dynamic table query
            $lessonPlans = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($lessonPlans);
            }

            return Inertia::render('Academic/LessonPlans', [
                'lessonPlans' => $lessonPlans,
                'filters' => $request->only(['search', 'sort', 'sortOrder', 'perPage', 'with_trashed']),
                'classLevels' => ClassLevel::where('school_id', $school->id)->select('id', 'name')->get(),
                'subjects' => Subject::where('school_id', $school->id)->select('id', 'name')->get(),
                'syllabusDetails' => SylabusDetail::where('school_id', $school->id)->select('id', 'title')->get(),
                'teachers' => Staff::where('school_id', $school->id)
                    ->whereHas('roles', fn($query) => $query->where('name', 'teacher'))
                    ->select('id', 'first_name', 'last_name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch lesson plans: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch lesson plans'], 500)
                : redirect()->back()->with('error', 'Failed to load lesson plans.');
        }
    }

    /**
     * Show the form for creating a new lesson plan.
     *
     * Renders the Academic/LessonPlanCreate Vue component.
     *
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function create()
    {
        Gate::authorize('create', LessonPlan::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            return Inertia::render('Academic/LessonPlanCreate', [
                'classLevels' => ClassLevel::where('school_id', $school->id)->select('id', 'name')->get(),
                'subjects' => Subject::where('school_id', $school->id)->select('id', 'name')->get(),
                'syllabusDetails' => SylabusDetail::where('school_id', $school->id)->select('id', 'title')->get(),
                'teachers' => Staff::where('school_id', $school->id)
                    ->whereHas('roles', fn($query) => $query->where('name', 'teacher'))
                    ->select('id', 'first_name', 'last_name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load lesson plan creation form: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load lesson plan creation form.');
        }
    }

    /**
     * Store a newly created lesson plan in storage.
     *
     * Validates the input, creates the lesson plan, attaches media, and sends notifications.
     *
     * @param Request $request The HTTP request containing lesson plan data.
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If creation fails.
     */
    public function store(Request $request)
    {
        Gate::authorize('create', LessonPlan::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'class_level_id' => 'required|exists:class_levels,id,school_id,' . $school->id,
                'subject_id' => 'required|exists:subjects,id,school_id,' . $school->id,
                'sylabus_detail_id' => 'nullable|exists:sylabus_details,id,school_id,' . $school->id,
                'topic' => 'required|string|max:255',
                'date' => 'required|date',
                'objective' => 'required|string',
                'material' => 'nullable|array',
                'assessment' => 'nullable|array',
                'staff_id' => 'required|exists:staff,id,school_id,' . $school->id,
                'media' => 'nullable|array',
                'media.*' => 'file|mimes:pdf,jpg,png|max:2048',
            ])->validate();

            // Create the lesson plan
            $lessonPlan = LessonPlan::create([
                'school_id' => $school->id,
                'class_level_id' => $validated['class_level_id'],
                'subject_id' => $validated['subject_id'],
                'sylabus_detail_id' => $validated['sylabus_detail_id'],
                'topic' => $validated['topic'],
                'date' => $validated['date'],
                'objective' => $validated['objective'],
                'material' => $validated['material'],
                'assessment' => $validated['assessment'],
                'staff_id' => $validated['staff_id'],
            ]);

            // Attach media if provided
            if ($request->hasFile('media')) {
                foreach ($request->file('media') as $file) {
                    $lessonPlan->addMedia($file)->toMediaCollection('lesson_plan_files');
                }
            }

            // Notify staff (admin/teacher roles)
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            Notification::send($users, new LessonPlanAction($lessonPlan, 'created'));

            return $request->wantsJson()
                ? response()->json(['message' => 'Lesson plan created successfully'], 201)
                : redirect()->route('lesson-plans.index')->with('success', 'Lesson plan created successfully.');
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to create lesson plan: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create lesson plan'], 500)
                : redirect()->back()->with('error', 'Failed to create lesson plan.');
        }
    }

    /**
     * Display the specified lesson plan.
     *
     * Loads the lesson plan with related data and returns a JSON response.
     *
     * @param Request $request The HTTP request.
     * @param LessonPlan $lessonPlan The lesson plan to display.
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception If no active school is found or lesson plan is not accessible.
     */
    public function show(Request $request, LessonPlan $lessonPlan)
    {
        Gate::authorize('view', $lessonPlan);

        try {
            $school = GetSchoolModel();
            if (!$school || $lessonPlan->school_id !== $school->id) {
                throw new \Exception('Lesson plan not found or not accessible.');
            }

            $lessonPlan->load(['classLevel', 'subject', 'sylabusDetail', 'staff', 'lessonPlanDetails', 'media']);

            return response()->json(['lesson_plan' => $lessonPlan]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch lesson plan: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch lesson plan'], 500);
        }
    }

    /**
     * Show the form for editing the specified lesson plan.
     *
     * Renders the Academic/LessonPlanEdit Vue component.
     *
     * @param LessonPlan $lessonPlan The lesson plan to edit.
     * @return \Inertia\Response
     * @throws \Exception If no active school is found or lesson plan is not accessible.
     */
    public function edit(LessonPlan $lessonPlan)
    {
        Gate::authorize('update', $lessonPlan);

        try {
            $school = GetSchoolModel();
            if (!$school || $lessonPlan->school_id !== $school->id) {
                throw new \Exception('Lesson plan not found or not accessible.');
            }

            $lessonPlan->load(['classLevel', 'subject', 'sylabusDetail', 'staff', 'media']);

            return Inertia::render('Academic/LessonPlanEdit', [
                'lessonPlan' => $lessonPlan,
                'classLevels' => ClassLevel::where('school_id', $school->id)->select('id', 'name')->get(),
                'subjects' => Subject::where('school_id', $school->id)->select('id', 'name')->get(),
                'syllabusDetails' => SylabusDetail::where('school_id', $school->id)->select('id', 'title')->get(),
                'teachers' => Staff::where('school_id', $school->id)
                    ->whereHas('roles', fn($query) => $query->where('name', 'teacher'))
                    ->select('id', 'first_name', 'last_name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load lesson plan edit form: ' . $e->getMessage());
            return redirect()->route('lesson-plans.index')->with('error', 'Failed to load lesson plan edit form.');
        }
    }

    /**
     * Update the specified lesson plan in storage.
     *
     * Validates the input, updates the lesson plan, syncs media, and sends notifications.
     *
     * @param Request $request The HTTP request containing updated lesson plan data.
     * @param LessonPlan $lessonPlan The lesson plan to update.
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If update fails.
     */
    public function update(Request $request, LessonPlan $lessonPlan)
    {
        Gate::authorize('update', $lessonPlan);

        try {
            $school = GetSchoolModel();
            if (!$school || $lessonPlan->school_id !== $school->id) {
                throw new \Exception('Lesson plan not found or not accessible.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'class_level_id' => 'required|exists:class_levels,id,school_id,' . $school->id,
                'subject_id' => 'required|exists:subjects,id,school_id,' . $school->id,
                'sylabus_detail_id' => 'nullable|exists:sylabus_details,id,school_id,' . $school->id,
                'topic' => 'required|string|max:255',
                'date' => 'required|date',
                'objective' => 'required|string',
                'material' => 'nullable|array',
                'assessment' => 'nullable|array',
                'staff_id' => 'required|exists:staff,id,school_id,' . $school->id,
                'media' => 'nullable|array',
                'media.*' => 'file|mimes:pdf,jpg,png|max:2048',
            ])->validate();

            // Update the lesson plan
            $lessonPlan->update([
                'class_level_id' => $validated['class_level_id'],
                'subject_id' => $validated['subject_id'],
                'sylabus_detail_id' => $validated['sylabus_detail_id'],
                'topic' => $validated['topic'],
                'date' => $validated['date'],
                'objective' => $validated['objective'],
                'material' => $validated['material'],
                'assessment' => $validated['assessment'],
                'staff_id' => $validated['staff_id'],
            ]);

            // Sync media if provided
            if ($request->hasFile('media')) {
                $lessonPlan->clearMediaCollection('lesson_plan_files');
                foreach ($request->file('media') as $file) {
                    $lessonPlan->addMedia($file)->toMediaCollection('lesson_plan_files');
                }
            }

            // Notify staff
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            Notification::send($users, new LessonPlanAction($lessonPlan, 'updated'));

            return $request->wantsJson()
                ? response()->json(['message' => 'Lesson plan updated successfully'])
                : redirect()->route('lesson-plans.index')->with('success', 'Lesson plan updated successfully.');
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to update lesson plan: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update lesson plan'], 500)
                : redirect()->back()->with('error', 'Failed to update lesson plan.');
        }
    }

    /**
     * Remove one or more lesson plans from storage (soft or force delete).
     *
     * Accepts an array of lesson plan IDs via JSON request, performs soft or force delete,
     * and sends notifications.
     *
     * @param Request $request The HTTP request containing an array of lesson plan IDs.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If deletion fails or lesson plans are not accessible.
     */
    public function destroy(Request $request)
    {
        Gate::authorize('delete', LessonPlan::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'exists:lesson_plans,id,school_id,' . $school->id,
                'force' => 'sometimes|boolean',
            ])->validate();

            // Notify before deletion
            $lessonPlans = LessonPlan::whereIn('id', $validated['ids'])
                ->where('school_id', $school->id)
                ->get();
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            foreach ($lessonPlans as $lessonPlan) {
                Notification::send($users, new LessonPlanAction($lessonPlan, 'deleted'));
            }

            // Perform soft or force delete
            $forceDelete = $request->boolean('force');
            $query = LessonPlan::whereIn('id', $validated['ids'])->where('school_id', $school->id);
            $deleted = $forceDelete ? $query->forceDelete() : $query->delete();

            $message = $deleted ? "$deleted lesson plan(s) deleted successfully" : "No lesson plans were deleted";

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('lesson-plans.index')->with('success', $message);
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to delete lesson plans: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete lesson plan(s)'], 500)
                : redirect()->back()->with('error', 'Failed to delete lesson plan(s).');
        }
    }

    /**
     * Restore one or more soft-deleted lesson plans.
     *
     * Accepts an array of lesson plan IDs via JSON request, restores them, and sends notifications.
     *
     * @param Request $request The HTTP request containing an array of lesson plan IDs.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If restoration fails or lesson plans are not accessible.
     */
    public function restore(Request $request)
    {
        Gate::authorize('restore', LessonPlan::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'exists:lesson_plans,id,school_id,' . $school->id,
            ])->validate();

            // Notify before restoration
            $lessonPlans = LessonPlan::onlyTrashed()
                ->whereIn('id', $validated['ids'])
                ->where('school_id', $school->id)
                ->get();
            $users = Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'teacher']))
                ->get();
            foreach ($lessonPlans as $lessonPlan) {
                Notification::send($users, new LessonPlanAction($lessonPlan, 'restored'));
            }

            // Restore the lesson plans
            $count = LessonPlan::onlyTrashed()
                ->whereIn('id', $validated['ids'])
                ->where('school_id', $school->id)
                ->restore();

            $message = $count ? "$count lesson plan(s) restored successfully" : "No lesson plans were restored";

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('lesson-plans.index')->with('success', $message);
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to restore lesson plans: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to restore lesson plan(s)'], 500)
                : redirect()->back()->with('error', 'Failed to restore lesson plan(s).');
        }
    }
}

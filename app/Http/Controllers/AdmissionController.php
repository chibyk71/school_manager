<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\ClassLevel;
use App\Models\SchoolSection;
use App\Models\Student\Admission;
use App\Models\Student\Student;
use App\Notifications\AdmissionAction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Controller for managing admissions in the school management system.
 *
 * Handles CRUD operations, including soft delete, force delete, and restore,
 * for admissions, ensuring proper authorization, validation, school scoping,
 * and notifications for a multi-tenant SaaS environment.
 *
 * @package App\Http\Controllers
 */
class AdmissionController extends Controller
{
    /**
     * Display a listing of admissions with search, filter, sort, and pagination.
     *
     * Uses the HasTableQuery trait to handle dynamic querying.
     * Renders the Student/Admissions Vue component or returns JSON for API requests.
     *
     * @param Request $request The HTTP request containing query parameters.
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     * @throws \Exception If query fails or no active school is found.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Admission::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Define extra fields for table query
            $extraFields = [
                'student' => ['field' => 'student.full_name', 'filterable' => true, 'sortable' => true, 'filterType' => 'text'],
                'class_level' => ['field' => 'class_level.name', 'filterable' => true, 'sortable' => true, 'filterType' => 'text'],
                'school_section' => ['field' => 'school_section.name', 'filterable' => true, 'sortable' => true, 'filterType' => 'text'],
                'academic_session' => ['field' => 'academic_session.name', 'filterable' => true, 'sortable' => true, 'filterType' => 'text'],
            ];

            // Build query
            $query = Admission::with([
                'student:id,first_name,last_name',
                'classLevel:id,name',
                'schoolSection:id,name',
                'academicSession:id,name',
            ])->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Apply dynamic table query
            $admissions = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($admissions);
            }

            return Inertia::render('Student/Admissions', [
                'admissions' => $admissions,
                'filters' => $request->only(['search', 'sort', 'sortOrder', 'perPage', 'with_trashed']),
                'students' => Student::where('school_id', $school->id)->select('id', 'first_name', 'last_name')->get(),
                'classLevels' => ClassLevel::where('school_id', $school->id)->select('id', 'name')->get(),
                'schoolSections' => SchoolSection::where('school_id', $school->id)->select('id', 'name')->get(),
                'academicSessions' => AcademicSession::where('school_id', $school->id)->select('id', 'name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch admissions: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch admissions'], 500)
                : redirect()->back()->with('error', 'Failed to load admissions.');
        }
    }

    /**
     * Show the form for creating a new admission.
     *
     * Renders the Student/AdmissionCreate Vue component.
     *
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function create()
    {
        $this->authorize('create', Admission::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            return Inertia::render('Student/AdmissionCreate', [
                'students' => Student::where('school_id', $school->id)->select('id', 'first_name', 'last_name')->get(),
                'classLevels' => ClassLevel::where('school_id', $school->id)->select('id', 'name')->get(),
                'schoolSections' => SchoolSection::where('school_id', $school->id)->select('id', 'name')->get(),
                'academicSessions' => AcademicSession::where('school_id', $school->id)->select('id', 'name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load admission creation form: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load admission creation form.');
        }
    }

    /**
     * Store a newly created admission in storage.
     *
     * Validates the input, creates the admission, and sends notifications.
     *
     * @param Request $request The HTTP request containing admission data.
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If creation fails.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Admission::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'student_id' => 'required|uuid|exists:students,id,school_id,' . $school->id,
                'class_level_id' => 'required|exists:class_levels,id,school_id,' . $school->id,
                'school_section_id' => 'required|exists:school_sections,id,school_id,' . $school->id,
                'academic_session_id' => 'required|exists:academic_sessions,id,school_id,' . $school->id,
                'roll_no' => 'required|string|max:255|unique:admissions,roll_no,NULL,id,school_id,' . $school->id,
                'status' => 'required|in:pending,approved,rejected',
                'configs' => 'nullable|array',
            ])->validate();

            // Create the admission
            $admission = Admission::create([
                'school_id' => $school->id,
                'student_id' => $validated['student_id'],
                'class_level_id' => $validated['class_level_id'],
                'school_section_id' => $validated['school_section_id'],
                'academic_session_id' => $validated['academic_session_id'],
                'roll_no' => $validated['roll_no'],
                'status' => $validated['status'],
                'configs' => $validated['configs'],
            ]);

            // Notify staff
            $users = \App\Models\Employee\Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'registrar']))
                ->get();
            Notification::send($users, new AdmissionAction($admission, 'created'));

            return $request->wantsJson()
                ? response()->json(['message' => 'Admission created successfully'], 201)
                : redirect()->route('admissions.index')->with('success', 'Admission created successfully.');
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to create admission: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create admission'], 500)
                : redirect()->back()->with('error', 'Failed to create admission.');
        }
    }

    /**
     * Display the specified admission.
     *
     * Loads the admission with related data and returns a JSON response.
     *
     * @param Request $request The HTTP request.
     * @param Admission $admission The admission to display.
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception If no active school is found or admission is not accessible.
     */
    public function show(Request $request, Admission $admission)
    {
        $this->authorize('view', $admission);

        try {
            $school = GetSchoolModel();
            if (!$school || $admission->school_id !== $school->id) {
                throw new \Exception('Admission not found or not accessible.');
            }

            $admission->load([
                'student:id,first_name,last_name',
                'classLevel:id,name',
                'schoolSection:id,name',
                'academicSession:id,name',
            ]);

            return response()->json(['admission' => $admission]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch admission: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch admission'], 500);
        }
    }

    /**
     * Show the form for editing the specified admission.
     *
     * Renders the Student/AdmissionEdit Vue component.
     *
     * @param Admission $admission The admission to edit.
     * @return \Inertia\Response
     * @throws \Exception If no active school is found or admission is not accessible.
     */
    public function edit(Admission $admission)
    {
        $this->authorize('update', $admission);

        try {
            $school = GetSchoolModel();
            if (!$school || $admission->school_id !== $school->id) {
                throw new \Exception('Admission not found or not accessible.');
            }

            $admission->load(['student', 'classLevel', 'schoolSection', 'academicSession']);

            return Inertia::render('Student/AdmissionEdit', [
                'admission' => $admission,
                'students' => Student::where('school_id', $school->id)->select('id', 'first_name', 'last_name')->get(),
                'classLevels' => ClassLevel::where('school_id', $school->id)->select('id', 'name')->get(),
                'schoolSections' => SchoolSection::where('school_id', $school->id)->select('id', 'name')->get(),
                'academicSessions' => AcademicSession::where('school_id', $school->id)->select('id', 'name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load admission edit form: ' . $e->getMessage());
            return redirect()->route('admissions.index')->with('error', 'Failed to load admission edit form.');
        }
    }

    /**
     * Update the specified admission in storage.
     *
     * Validates the input, updates the admission, and sends notifications.
     *
     * @param Request $request The HTTP request containing updated admission data.
     * @param Admission $admission The admission to update.
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If update fails.
     */
    public function update(Request $request, Admission $admission)
    {
        $this->authorize('update', $admission);

        try {
            $school = GetSchoolModel();
            if (!$school || $admission->school_id !== $school->id) {
                throw new \Exception('Admission not found or not accessible.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'student_id' => 'required|uuid|exists:students,id,school_id,' . $school->id,
                'class_level_id' => 'required|exists:class_levels,id,school_id,' . $school->id,
                'school_section_id' => 'required|exists:school_sections,id,school_id,' . $school->id,
                'academic_session_id' => 'required|exists:academic_sessions,id,school_id,' . $school->id,
                'roll_no' => 'required|string|max:255|unique:admissions,roll_no,' . $admission->id . ',id,school_id,' . $school->id,
                'status' => 'required|in:pending,approved,rejected',
                'configs' => 'nullable|array',
            ])->validate();

            // Update the admission
            $admission->update([
                'student_id' => $validated['student_id'],
                'class_level_id' => $validated['class_level_id'],
                'school_section_id' => $validated['school_section_id'],
                'academic_session_id' => $validated['academic_session_id'],
                'roll_no' => $validated['roll_no'],
                'status' => $validated['status'],
                'configs' => $validated['configs'],
            ]);

            // Notify staff
            $users = \App\Models\Employee\Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'registrar']))
                ->get();
            Notification::send($users, new AdmissionAction($admission, 'updated'));

            return $request->wantsJson()
                ? response()->json(['message' => 'Admission updated successfully'])
                : redirect()->route('admissions.index')->with('success', 'Admission updated successfully.');
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to update admission: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update admission'], 500)
                : redirect()->back()->with('error', 'Failed to update admission.');
        }
    }

    /**
     * Remove one or more admissions from storage (soft or force delete).
     *
     * Accepts an array of admission IDs via JSON request, performs soft or force delete,
     * and sends notifications.
     *
     * @param Request $request The HTTP request containing an array of admission IDs.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If deletion fails or admissions are not accessible.
     */
    public function destroy(Request $request)
    {
        $this->authorize('delete', Admission::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'uuid|exists:admissions,id,school_id,' . $school->id,
                'force' => 'sometimes|boolean',
            ])->validate();

            // Notify before deletion
            $admissions = Admission::whereIn('id', $validated['ids'])
                ->where('school_id', $school->id)
                ->get();
            $users = \App\Models\Employee\Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'registrar']))
                ->get();
            foreach ($admissions as $admission) {
                Notification::send($users, new AdmissionAction($admission, 'deleted'));
            }

            // Perform soft or force delete
            $forceDelete = $request->boolean('force');
            $query = Admission::whereIn('id', $validated['ids'])->where('school_id', $school->id);
            $deleted = $forceDelete ? $query->forceDelete() : $query->delete();

            $message = $deleted ? "$deleted admission(s) deleted successfully" : "No admissions were deleted";

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('admissions.index')->with('success', $message);
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to delete admissions: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete admission(s)'], 500)
                : redirect()->back()->with('error', 'Failed to delete admission(s).');
        }
    }

    /**
     * Restore one or more soft-deleted admissions.
     *
     * Accepts an array of admission IDs via JSON request, restores them, and sends notifications.
     *
     * @param Request $request The HTTP request containing an array of admission IDs.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If restoration fails or admissions are not accessible.
     */
    public function restore(Request $request)
    {
        $this->authorize('restore', Admission::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            // Validate request data
            $validated = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'uuid|exists:admissions,id,school_id,' . $school->id,
            ])->validate();

            // Notify before restoration
            $admissions = Admission::onlyTrashed()
                ->whereIn('id', $validated['ids'])
                ->where('school_id', $school->id)
                ->get();
            $users = \App\Models\Employee\Staff::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'registrar']))
                ->get();
            foreach ($admissions as $admission) {
                Notification::send($users, new AdmissionAction($admission, 'restored'));
            }

            // Restore the admissions
            $count = Admission::onlyTrashed()
                ->whereIn('id', $validated['ids'])
                ->where('school_id', $school->id)
                ->restore();

            $message = $count ? "$count admission(s) restored successfully" : "No admissions were restored";

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('admissions.index')->with('success', $message);
        } catch (ValidationException $e) {
            return $request->wantsJson()
                ? response()->json(['errors' => $e->errors()], 422)
                : redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to restore admissions: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to restore admission(s)'], 500)
                : redirect()->back()->with('error', 'Failed to restore admission(s).');
        }
    }
}

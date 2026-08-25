<?php

namespace App\Http\Controllers\Student;

use App\Http\Requests\Student\EnrollStudentRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use App\Http\Resources\Student\StudentResource;
use App\Models\Academic\ClassSection;
use App\Models\Academic\Student;
use App\Models\Guardian;
use App\Models\SchoolSection;
use App\Services\Student\StudentEnrollmentService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * StudentController – Full CRUD for Enrolled Students
 *
 * Manages the student record lifecycle after enrollment: viewing, editing,
 * creating new direct-enrollment students, and soft-deletion.
 *
 * ── Authorization ─────────────────────────────────────────────────────────────
 * Every action is gated through StudentPolicy:
 *   - viewAny     → students.view
 *   - view        → students.view  (also allows student self-view)
 *   - create      → students.create
 *   - store       → students.create
 *   - update      → students.update
 *   - destroy     → students.delete
 *
 * ── Architecture Notes ───────────────────────────────────────────────────────
 * Direct enrollment (bypassing the application pipeline) goes through
 * StudentEnrollmentService. Update operations delegate to UserService
 * for the User + Profile layer and handle Student-specific fields inline.
 *
 * Custom fields, guardian links, and class section assignments are synced
 * inside DB transactions to ensure atomicity.
 *
 * ── Multi-Tenant Safety ──────────────────────────────────────────────────────
 * BelongsToSchool global scope on Student means all queries are automatically
 * scoped. The policy provides a second layer of defense for individual records.
 *
 * ── Fits into the Student Management Module ──────────────────────────────────
 * - Route prefix: /students
 * - Frontend pages: Students/Index.vue, Students/Create.vue,
 *   Students/Show.vue, Students/Edit.vue
 * - Policy: App\Policies\Student\StudentPolicy
 */
class StudentController
{
    public function __construct(
        protected UserService             $userService,
        protected StudentEnrollmentService $enrollmentService,
    ) {}

    /**
     * List all enrolled students with DataTable support.
     * GET /students
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Student::class);

        try {
            $result = Student::query()
                ->with([
                    'profile:id,user_id,first_name,last_name,gender,date_of_birth,phone',
                    'profile.user:id,enrollment_id,is_active',
                    'currentClassSection.classLevel:id,name',
                ])
                ->tableQuery($request);

            return Inertia::render('Students/Index', [
                'students'         => $result['data'],
                'totalRecords'     => $result['totalRecords'],
                'columns'          => $result['columns'],
                'globalFilterables'=> $result['globalFilterables'],
                'filters'          => $request->only(['search', 'status', 'section']),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to list students', ['error' => $e->getMessage()]);
            return back()->withErrors(['error' => 'Unable to load students.']);
        }
    }

    /**
     * Show the direct-enrollment creation form.
     * GET /students/create
     */
    public function create(Request $request)
    {
        Gate::authorize('create', Student::class);

        try {
            $school = GetSchoolModel();

            return Inertia::render('Students/Create', [
                'schoolSections' => SchoolSection::select('id', 'name')->get(),
                'classSections'  => ClassSection::with('classLevel:id,name')
                    ->select('id', 'name', 'class_level_id')
                    ->get(),
                'guardians'      => Guardian::with('profile:id,first_name,last_name')
                    ->select('id', 'profile_id')
                    ->get(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to load student create form', ['error' => $e->getMessage()]);
            return back()->withErrors(['error' => 'Unable to load the form.']);
        }
    }

    /**
     * Directly enroll a new student (bypass the application pipeline).
     * POST /students
     *
     * Uses StudentEnrollmentService which creates Profile + Student atomically.
     * Optional login account creation if create_login is true.
     */
    public function store(EnrollStudentRequest $request)
    {
        Gate::authorize('store', Student::class);

        try {
            $student = $this->enrollmentService->(
                data:          $request->validated(),
                createLogin:   $request->boolean('create_login', false),
            );

            // Custom fields and guardian sync after enrollment
            DB::transaction(function () use ($request, $student) {
                if ($request->filled('custom_fields')) {
                    $student->saveCustomFieldResponses($request->validated('custom_fields'));
                }

                if ($request->filled('guardian_ids')) {
                    $student->guardians()->sync($request->validated('guardian_ids'));
                }
            });

            return redirect()
                ->route('students.show', $student)
                ->with('success', 'Student enrolled successfully.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();

        } catch (\Exception $e) {
            Log::error('Failed to enroll student', [
                'user_id' => auth()->id(),
                'error'   => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    /**
     * Display a student's full profile.
     * GET /students/{student}
     */
    public function show(Student $student)
    {
        Gate::authorize('view', $student);

        $student->load([
            'profile.user:id,enrollment_id,email,is_active,last_login_at',
            'profile.addresses',
            'currentClassSection.classLevel',
            'guardians.profile:id,first_name,last_name,phone,email',
            'sessionPlacements.academicSession',
            'sessionPlacements.classSection.classLevel',
        ]);

        return Inertia::render('Students/Show', [
            'student' => new StudentResource($student),
        ]);
    }

    /**
     * Show the student edit form.
     * GET /students/{student}/edit
     */
    public function edit(Student $student)
    {
        Gate::authorize('update', $student);

        $student->load([
            'profile:id,user_id,first_name,middle_name,last_name,gender,date_of_birth,phone,email',
            'guardians:id,profile_id',
            'currentClassSection:id,name,class_level_id',
        ]);

        return Inertia::render('Students/Edit', [
            'student'        => new StudentResource($student),
            'schoolSections' => SchoolSection::select('id', 'name')->get(),
            'classSections'  => ClassSection::with('classLevel:id,name')
                ->select('id', 'name', 'class_level_id')
                ->get(),
            'guardians'      => Guardian::with('profile:id,first_name,last_name')
                ->select('id', 'profile_id')
                ->get(),
        ]);
    }

    /**
     * Update a student's profile and enrollment details.
     * PUT/PATCH /students/{student}
     */
    public function update(UpdateStudentRequest $request, Student $student)
    {
        Gate::authorize('update', $student);

        try {
            DB::transaction(function () use ($request, $student) {
                // Update User + Profile layer via UserService
                $this->userService->update($student->profile->user, $request->validated());

                // Update student-specific fields
                $student->update(
                    $request->only(['status', 'admission_type', 'section_id', 'notes'])
                );

                if ($request->filled('custom_fields')) {
                    $student->saveCustomFieldResponses($request->validated('custom_fields'));
                }

                if ($request->has('guardian_ids')) {
                    $student->guardians()->sync($request->validated('guardian_ids', []));
                }
            });

            return redirect()
                ->route('students.show', $student)
                ->with('success', 'Student profile updated successfully.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();

        } catch (\Exception $e) {
            Log::error('Failed to update student', [
                'student_id' => $student->id,
                'user_id'    => auth()->id(),
                'error'      => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => 'Unable to update student.'])->withInput();
        }
    }

    /**
     * Soft-delete a student record.
     * DELETE /students/{student}
     */
    public function destroy(Request $request, Student $student)
    {
        Gate::authorize('delete', $student);

        try {
            $student->delete();

            if ($request->wantsJson()) {
                return response()->json(['message' => 'Student deleted successfully.']);
            }

            return redirect()
                ->route('students.index')
                ->with('success', 'Student moved to trash.');

        } catch (\Exception $e) {
            Log::error('Failed to delete student', [
                'student_id' => $student->id,
                'user_id'    => auth()->id(),
                'error'      => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => 'Unable to delete student.']);
        }
    }
}

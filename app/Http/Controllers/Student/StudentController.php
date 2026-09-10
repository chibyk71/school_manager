<?php

namespace App\Http\Controllers\Student;

use App\Http\Requests\Student\EnrollStudentRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use App\Http\Resources\Student\StudentResource;
use App\Models\Student\Student;
use App\Services\Student\EnrollmentService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * StudentController – CRUD for enrolled Student capacity records.
 *
 * Phase 8: student creation goes through the canonical EnrollmentService
 * (start → biodata → finalize). There is no parallel StudentEnrollmentService path.
 */
class StudentController
{
    public function __construct(
        protected UserService $userService,
        protected EnrollmentService $enrollmentService,
    ) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Student::class);

        try {
            $result = Student::query()
                ->with([
                    'profile:id,user_id,first_name,last_name,gender,date_of_birth,phone',
                    'profile.user:id,email,is_active',
                ])
                ->tableQuery($request);

            return Inertia::render('UserManagement/Students/Index', [
                'students' => $result['data'],
                'totalRecords' => $result['totalRecords'],
                'columns' => $result['columns'],
                'globalFilterables' => $result['globalFilterables'],
                'filters' => $request->only(['search', 'status', 'section']),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to list students', ['error' => $e->getMessage()]);
            return back()->withErrors(['error' => 'Unable to load students.']);
        }
    }

    public function create(Request $request)
    {
        Gate::authorize('create', Student::class);

        return redirect()
            ->route('enrollments.index')
            ->with('info', 'Create students through the Enrollment workflow (start enrollment, complete requirements, then finalize).');
    }

    public function store(EnrollStudentRequest $request)
    {
        Gate::authorize('create', Student::class);

        $school = GetSchoolModel();
        if (!$school) {
            return back()->withErrors(['error' => 'No active school context.']);
        }

        try {
            $validated = $request->validated();
            $biodata = array_filter([
                'first_name' => $validated['first_name'] ?? null,
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'class_level_id' => $validated['class_level_id'] ?? null,
                'class_section_id' => $validated['class_section_id'] ?? ($validated['section_id'] ?? null),
            ], fn ($v) => $v !== null && $v !== '');

            $sessionId = $validated['academic_session_id']
                ?? $school->current_academic_session_id
                ?? null;

            if (!$sessionId) {
                throw ValidationException::withMessages([
                    'academic_session_id' => 'Academic session is required for enrollment.',
                ]);
            }

            $enrollment = $this->enrollmentService->start($school, $request->user(), [
                'academic_session_id' => $sessionId,
                'biodata' => $biodata,
                'source' => 'direct',
                'notes' => $validated['notes'] ?? null,
            ]);

            if (!empty($biodata)) {
                $enrollment = $this->enrollmentService->updateBiodata(
                    $enrollment,
                    $request->user(),
                    $biodata
                );
            }

            $readiness = $this->enrollmentService->evaluateReadiness($enrollment);
            if (!empty($readiness['blockers'])) {
                return redirect()
                    ->route('enrollments.show', $enrollment)
                    ->with('warning', 'Enrollment started. Complete remaining requirements before finalization.')
                    ->with('readiness', $readiness);
            }

            $enrollment = $this->enrollmentService->finalize($enrollment, $request->user());

            return redirect()
                ->route('students.show', $enrollment->student_id)
                ->with('success', 'Student enrolled and finalized successfully.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to enroll student via canonical path', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function show(Student $student)
    {
        Gate::authorize('view', $student);

        $school = GetSchoolModel();
        if ($school && (string) $student->school_id !== (string) $school->id) {
            abort(404);
        }

        $student->load([
            'profile.user:id,email,is_active,last_login_at',
            'profile.addresses',
            'guardians.profile:id,first_name,last_name,phone,email',
            'sessionPlacements.academicSession',
            'sessionPlacements.classSection.classLevel',
        ]);

        return Inertia::render('UserManagement/Students/Show', [
            'student' => new StudentResource($student),
        ]);
    }

    public function edit(Student $student)
    {
        Gate::authorize('update', $student);

        $school = GetSchoolModel();
        if ($school && (string) $student->school_id !== (string) $school->id) {
            abort(404);
        }

        $student->load(['profile', 'guardians']);

        return Inertia::render('UserManagement/Students/Show', [
            'student' => new StudentResource($student),
        ]);
    }

    public function update(UpdateStudentRequest $request, Student $student)
    {
        Gate::authorize('update', $student);

        $school = GetSchoolModel();
        if ($school && (string) $student->school_id !== (string) $school->id) {
            abort(404);
        }

        try {
            DB::transaction(function () use ($request, $student) {
                $data = $request->validated();
                $student->fill(collect($data)->only([
                    'admission_type', 'notes', 'status', 'status_reason',
                ])->filter()->all());
                $student->save();

                if ($student->profile && $request->filled('first_name')) {
                    $student->profile->fill(collect($data)->only([
                        'first_name', 'middle_name', 'last_name', 'gender',
                        'date_of_birth', 'phone', 'email',
                    ])->filter()->all());
                    $student->profile->save();
                }

                if ($request->filled('custom_fields')) {
                    $student->saveCustomFieldResponses($request->validated('custom_fields'));
                }

                if ($request->filled('guardian_ids')) {
                    $student->guardians()->sync($request->validated('guardian_ids'));
                }
            });

            return redirect()
                ->route('students.show', $student)
                ->with('success', 'Student updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update student', [
                'student_id' => $student->id,
                'error' => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => 'Unable to update student.'])->withInput();
        }
    }

    public function destroy(Request $request, Student $student)
    {
        Gate::authorize('delete', $student);

        $school = GetSchoolModel();
        if ($school && (string) $student->school_id !== (string) $school->id) {
            abort(404);
        }

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
                'error' => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => 'Unable to delete student.']);
        }
    }
}

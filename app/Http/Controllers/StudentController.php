<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Models\Academic\Student;
use App\Models\Academic\ClassSection;
use App\Models\Guardian;
use App\Models\SchoolSection;
use App\Services\UserService;
use App\Support\ColumnDefinitionHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * StudentController – Fully refactored for the new User + Profile architecture
 */
class StudentController extends BaseSchoolController
{
    public function __construct(protected UserService $userService) {}

    /**
     * Display a listing of students.
     */
    public function index(Request $request)
    {
        try {
            $school = $this->getActiveSchool();

            $students = Student::where('school_id', $school->id)
                ->with([
                    'user:id,name,email,enrollment_id',
                    'schoolSection:id,name',
                    'guardians.user:id,name',
                    'classSections:id,name',
                ])
                ->withCustomFields()
                ->tableQuery($request);

            return Inertia::render('UserManagement/Students/Index', [
                'students' => $students,
                'columns'  => ColumnDefinitionHelper::fromModel(new Student()),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch students: ' . $e->getMessage());
            return $this->respondWithError($request, 'Unable to load students.');
        }
    }

    /**
     * Show form for creating a new student.
     */
    public function create(Request $request)
    {
        try {
            $school = $this->getActiveSchool();

            return Inertia::render('UserManagement/Students/Create', [
                'customFields'    => $this->getCustomFieldsForForm($school->id, Student::class),
                'schoolSections'  => SchoolSection::where('school_id', $school->id)->get(['id', 'name']),
                'guardians'       => Guardian::where('school_id', $school->id)
                    ->with('user:id,name,email')
                    ->get(['id', 'user_id']),
                'classSections'   => ClassSection::where('school_id', $school->id)
                    ->with('classLevel:id,name')
                    ->get(['id', 'name', 'class_level_id']),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load student creation form: ' . $e->getMessage());
            return $this->respondWithError($request, 'Unable to load creation form.');
        }
    }

    /**
     * Store a new student using UserService.
     */
    public function store(StoreStudentRequest $request)
    {
        try {
            $school = $this->getActiveSchool();
            $data   = $request->validated();

            // Enrich data for UserService
            $data['profile_type'] = 'student';
            $data['profilable'] = [
                'school_id'         => $school->id,
                'school_section_id' => $data['school_section_id'] ?? null,
            ];

            // Create user + profile + student record
            $user    = $this->userService->create($data);
            $student = $user->student; // Thanks to HasProfile trait

            DB::transaction(function () use ($data, $student) {
                // Custom fields
                if (!empty($data['custom_fields'])) {
                    $student->saveCustomFieldResponses($data['custom_fields']);
                }

                // Sync guardians
                if (!empty($data['guardian_ids'])) {
                    $student->guardians()->sync($data['guardian_ids']);
                }

                // Sync class sections
                if (!empty($data['class_section_ids'])) {
                    $student->classSections()->sync($data['class_section_ids']);
                }
            });

            return $this->respondWithSuccess(
                $request,
                'Student created successfully.',
                'students.index'
            );
        } catch (\Exception $e) {
            Log::error('Failed to create student: ' . $e->getMessage());
            return $this->respondWithError($request, 'Unable to create student: ' . $e->getMessage());
        }
    }

    /**
     * Display a single student.
     */
    public function show(Request $request, Student $student)
    {
        try {
            Gate::authorize('view', $student);

            $student->load([
                'user',
                'schoolSection',
                'guardians.user',
                'classSections',
                'customFields',
            ]);

            return Inertia::render('UserManagement/Students/Show', [
                'student' => $student,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load student ID ' . $student->id . ': ' . $e->getMessage());
            return $this->respondWithError($request, 'Unable to load student.');
        }
    }

    /**
     * Show edit form.
     */
    public function edit(Request $request, Student $student)
    {
        try {
            Gate::authorize('update', $student);
            $school = $this->getActiveSchool();

            $student->load([
                'user',
                'schoolSection',
                'guardians.user',
                'classSections',
            ]);

            return Inertia::render('UserManagement/Students/Edit', [
                'student'         => $student,
                'customFields'    => $this->getCustomFieldsForForm($school->id, Student::class),
                'schoolSections'  => SchoolSection::where('school_id', $school->id)->get(['id', 'name']),
                'guardians'       => Guardian::where('school_id', $school->id)
                    ->with('user:id,name,email')
                    ->get(['id', 'user_id']),
                'classSections'   => ClassSection::where('school_id', $school->id)
                    ->with('classLevel:id,name')
                    ->get(['id', 'name', 'class_level_id']),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load student edit form: ' . $e->getMessage());
            return $this->respondWithError($request, 'Unable to load edit form.');
        }
    }

    /**
     * Update student using UserService.
     */
    public function update(UpdateStudentRequest $request, Student $student)
    {
        try {
            Gate::authorize('update', $student);
            $data = $request->validated();
            $user = $student->user;

            // Update core user + profile
            $this->userService->update($user, $data);

            DB::transaction(function () use ($data, $student) {
                $student->update([
                    'school_section_id' => $data['school_section_id'] ?? $student->school_section_id,
                ]);

                if (!empty($data['custom_fields'])) {
                    $student->saveCustomFieldResponses($data['custom_fields']);
                }

                if (isset($data['guardian_ids'])) {
                    $student->guardians()->sync($data['guardian_ids']);
                }

                if (isset($data['class_section_ids'])) {
                    $student->classSections()->sync($data['class_section_ids']);
                }
            });

            return $this->respondWithSuccess(
                $request,
                'Student updated successfully.',
                'students.index'
            );
        } catch (\Exception $e) {
            Log::error('Failed to update student ID ' . $student->id . ': ' . $e->getMessage());
            return $this->respondWithError($request, 'Unable to update student.');
        }
    }

    /**
     * Delete student.
     */
    public function destroy(Request $request, Student $student)
    {
        try {
            Gate::authorize('delete', $student);
            $student->delete();

            return $request->wantsJson()
                ? response()->json(['success' => true, 'message' => 'Student deleted successfully.'])
                : $this->respondWithSuccess($request, 'Student deleted successfully.', 'students.index');
        } catch (\Exception $e) {
            Log::error('Failed to delete student ID ' . $student->id . ': ' . $e->getMessage());
            return $this->respondWithError($request, 'Unable to delete student.', 500);
        }
    }
}

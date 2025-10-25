<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Models\Academic\Student;
use App\Models\School;
use App\Support\ColumnDefinitionHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Controller for managing students in the school management system.
 *
 * Handles CRUD operations for students, including custom fields, guardians, and class sections.
 * Scoped to the active school for multi-tenancy.
 *
 * @package App\Http\Controllers
 */
class StudentController extends Controller
{
    /**
     * Display a listing of students.
     *
     * @param Request $request
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        try {
            $school = $this->getActiveSchool();
            $students = Student::where('school_id', $school->id)
                ->with(['user:id,name,email,enrollment_id', 'schoolSection', 'guardians', 'classSections'])
                ->withCustomFields();

            return Inertia::render('UserManagement/Students/Index', [
                'students' => $students,
                'columns' => ColumnDefinitionHelper::fromModel(new Student()),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch students: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Unable to load students: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for creating a new student.
     *
     * @return \Inertia\Response
     */
    public function create()
    {
        try {
            $school = $this->getActiveSchool();
            $customFields = Student::getCustomFieldsForForm($school->id, 'App\Models\Academic\Student');

            return Inertia::render('UserManagement/Students/Create', [
                'customFields' => $customFields,
                'schoolSections' => \App\Models\SchoolSection::where('school_id', $school->id)->get(),
                'guardians' => \App\Models\Guardian::where('school_id', $school->id)->with('user')->get(),
                'classSections' => \App\Models\Academic\ClassSection::where('school_id', $school->id)->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load student creation form: ' . $e->getMessage());
            return redirect()->route('students.index')->with('error', 'Unable to load creation form: ' . $e->getMessage());
        }
    }

    /**
     * Store a newly created student in storage.
     *
     * @param StoreStudentRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(StoreStudentRequest $request)
    {
        try {
            DB::transaction(function () use ($request) {
                $school = $this->getActiveSchool();
                $data = $request->validated();

                // Create student
                $student = Student::create([
                    'user_id' => $data['user_id'],
                    'school_id' => $school->id,
                    'school_section_id' => $data['school_section_id'],
                ]);

                // Save custom fields
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

            return redirect()->route('students.index')->with('success', 'Student created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create student: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to create student: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified student.
     *
     * @param Student $student
     * @return \Inertia\Response
     */
    public function show(Student $student)
    {
        try {
            permitted('view-student');
            $student->load(['user', 'schoolSection', 'guardians', 'classSections', 'customFields']);

            return Inertia::render('UserManagement/Students/Show', [
                'student' => $student,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch student ID ' . $student->id . ': ' . $e->getMessage());
            return redirect()->route('students.index')->with('error', 'Unable to load student: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified student.
     *
     * @param Student $student
     * @return \Inertia\Response
     */
    public function edit(Student $student)
    {
        try {
            $this->authorize('update', $student);
            $school = $this->getActiveSchool();
            $customFields = Student::getCustomFieldsForForm($school->id, 'App\Models\Academic\Student');

            return Inertia::render('UserManagement/Students/Edit', [
                'student' => $student->load(['user', 'schoolSection', 'guardians', 'classSections']),
                'customFields' => $customFields,
                'schoolSections' => \App\Models\SchoolSection::where('school_id', $school->id)->get(),
                'guardians' => \App\Models\Guardian::where('school_id', $school->id)->with('user')->get(),
                'classSections' => \App\Models\Academic\ClassSection::where('school_id', $school->id)->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load student edit form for ID ' . $student->id . ': ' . $e->getMessage());
            return redirect()->route('students.index')->with('error', 'Unable to load edit form: ' . $e->getMessage());
        }
    }

    /**
     * Update the specified student in storage.
     *
     * @param UpdateStudentRequest $request
     * @param Student $student
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(UpdateStudentRequest $request, Student $student)
    {
        try {
            $this->authorize('update', $student);

            DB::transaction(function () use ($request, $student) {
                $data = $request->validated();

                // Update student
                $student->update([
                    'user_id' => $data['user_id'] ?? $student->user_id,
                    'school_section_id' => $data['school_section_id'] ?? $student->school_section_id,
                ]);

                // Save custom fields
                if (!empty($data['custom_fields'])) {
                    $student->saveCustomFieldResponses($data['custom_fields']);
                }

                // Sync guardians
                if (isset($data['guardian_ids'])) {
                    $student->guardians()->sync($data['guardian_ids']);
                }

                // Sync class sections
                if (isset($data['class_section_ids'])) {
                    $student->classSections()->sync($data['class_section_ids']);
                }
            });

            return redirect()->route('students.index')->with('success', 'Student updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update student ID ' . $student->id . ': ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to update student: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified student from storage.
     *
     * @param Student $student
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Student $student)
    {
        try {
            $this->authorize('delete', $student);
            $student->delete();

            return response()->json([
                'success' => true,
                'message' => 'Student deleted successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete student ID ' . $student->id . ': ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Unable to delete student: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the active school model.
     *
     * @return \App\Models\School
     * @throws \Exception
     */
    protected function getActiveSchool(): School
    {
        $school = GetSchoolModel();
        if (!$school) {
            throw new \Exception('No active school found.');
        }
        return $school;
    }

    /**
     * Get custom fields for a form, scoped to the school and model type.
     *
     * @param int $schoolId
     * @param string $modelType
     * @return \Illuminate\Support\Collection
     */
    public static function getCustomFieldsForForm(int $schoolId, string $modelType)
    {
        return \App\Models\CustomField::where('school_id', $schoolId)
            ->where('model_type', $modelType)
            ->orderBy('sort', 'asc')
            ->get()
            ->map(function ($field) {
                return [
                    'id' => $field->id,
                    'name' => $field->name,
                    'label' => $field->label,
                    'field_type' => $field->field_type,
                    'options' => $field->options,
                    'required' => $field->required,
                    'placeholder' => $field->placeholder,
                    'hint' => $field->hint,
                ];
            });
    }
}
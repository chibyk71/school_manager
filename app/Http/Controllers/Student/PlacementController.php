<?php
namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\Student\Enrollment;
use App\Models\Student\Student;
use App\Services\Student\PlacementAllocationService;
use App\Services\Student\RegistrationNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PlacementController extends Controller
{
    public function __construct(
        protected PlacementAllocationService $allocation,
        protected RegistrationNumberService $registrationNumbers
    ) {}

    public function allocate(Request $request, Enrollment $enrollment)
    {
        Gate::authorize('finalize', $enrollment);
        $data = $request->validate([
            'class_level_id' => ['nullable', 'uuid'],
            'class_section_id' => ['nullable', 'uuid'],
            'capacity_override' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);
        $school = School::query()->findOrFail($enrollment->school_id);
        $student = $enrollment->student;
        if (!$student) {
            abort(422, 'Enrollment has no student; finalize first.');
        }
        $placement = $this->allocation->allocateForEnrollment($enrollment, $student, $school, $request->user(), $data);
        return response()->json(['placement' => $placement]);
    }

    public function manual(Request $request, Student $student)
    {
        Gate::authorize('placements.manage');
        $data = $request->validate([
            'class_level_id' => ['required', 'uuid'],
            'class_section_id' => ['required', 'uuid'],
            'academic_session_id' => ['nullable', 'uuid'],
            'capacity_override' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
            'enrollment_id' => ['nullable', 'uuid'],
        ]);
        $school = School::query()->findOrFail($student->school_id);
        $placement = $this->allocation->placeManually($student, $school, $data['class_level_id'], $data['class_section_id'], $request->user(), $data);
        return response()->json(['placement' => $placement]);
    }

    public function regenerateRegistration(Request $request, Student $student)
    {
        Gate::authorize('placements.regenerate_registration_number');
        $placement = $student->currentPlacement;
        if (!$placement) {
            abort(422, 'Student has no current placement.');
        }
        $school = School::query()->findOrFail($student->school_id);
        $number = $this->registrationNumbers->regenerate($student, $school, $placement, $request->user(), $request->input('notes'));
        return response()->json([
            'registration_number' => $number,
            'history' => $this->registrationNumbers->history($student, $school->id),
        ]);
    }

    /**
     * Phase 6 — move student to another section in the same class level/session.
     */
    public function changeSection(Request $request, Student $student)
    {
        Gate::authorize('placements.change_section');
        $data = $request->validate([
            'class_section_id' => ['required', 'uuid'],
            'capacity_override' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'enrollment_id' => ['nullable', 'uuid'],
        ]);
        $school = School::query()->findOrFail($student->school_id);
        $placement = $this->allocation->changeSection(
            $student,
            $school,
            $data['class_section_id'],
            $request->user(),
            $data
        );

        return response()->json([
            'placement' => $placement,
            'admission_number' => $student->fresh()->admission_number,
            'registration_number' => $this->registrationNumbers->currentNumber($student, $school->id),
        ]);
    }

    /**
     * Phase 6 — administrative class-level move (not formal promotion).
     */
    public function changeClass(Request $request, Student $student)
    {
        Gate::authorize('placements.change_class');
        $data = $request->validate([
            'class_level_id' => ['required', 'uuid'],
            'class_section_id' => ['required', 'uuid'],
            'capacity_override' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'enrollment_id' => ['nullable', 'uuid'],
        ]);
        $school = School::query()->findOrFail($student->school_id);
        $placement = $this->allocation->changeClass(
            $student,
            $school,
            $data['class_level_id'],
            $data['class_section_id'],
            $request->user(),
            $data
        );

        return response()->json([
            'placement' => $placement,
            'admission_number' => $student->fresh()->admission_number,
            'registration_number' => $this->registrationNumbers->currentNumber($student, $school->id),
        ]);
    }

    public function history(Student $student)
    {
        Gate::authorize('view', $student);
        return response()->json([
            'placements' => $student->sessionPlacements()->orderByDesc('enrolled_at')->get(),
            'registration_numbers' => $this->registrationNumbers->history($student, $student->school_id),
            'admission_number' => $student->admission_number,
            'current_registration_number' => $this->registrationNumbers->currentNumber($student, $student->school_id),
        ]);
    }
}

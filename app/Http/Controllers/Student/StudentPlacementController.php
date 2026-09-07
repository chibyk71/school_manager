<?php

namespace App\Http\Controllers\Student;

use App\Http\Requests\Student\PlaceStudentRequest;
use App\Models\Student\Student;
use App\Services\Student\PlacementAllocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * StudentPlacementController – HTTP adapter for placement on existing students.
 *
 * Phase 8: delegates entirely to PlacementAllocationService (canonical).
 */
class StudentPlacementController
{
    public function __construct(
        protected PlacementAllocationService $allocation
    ) {}

    public function store(PlaceStudentRequest $request, Student $student)
    {
        Gate::authorize('place', $student);

        $school = GetSchoolModel();
        if (!$school || $student->school_id !== $school->id) {
            abort(404);
        }

        try {
            $data = $request->validated();
            $sectionId = $data['class_section_id'];
            $levelId = $data['class_level_id']
                ?? \App\Models\Academic\ClassSection::query()->whereKey($sectionId)->value('class_level_id');

            if (!$levelId) {
                throw ValidationException::withMessages([
                    'class_section_id' => 'Class section has no class level.',
                ]);
            }

            $placement = $this->allocation->placeManually(
                $student,
                $school,
                (string) $levelId,
                (string) $sectionId,
                $request->user(),
                [
                    'academic_session_id' => $data['academic_session_id'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'capacity_override' => (bool) ($data['capacity_override'] ?? false),
                ]
            );

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Student placed successfully.',
                    'placement' => $placement,
                ]);
            }

            return back()->with('success', 'Student placed successfully.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to place student', [
                'student_id' => $student->id,
                'error' => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function destroy(Request $request, Student $student)
    {
        Gate::authorize('place', $student);

        $school = GetSchoolModel();
        if (!$school || $student->school_id !== $school->id) {
            abort(404);
        }

        try {
            $updated = \App\Models\Student\StudentSessionPlacement::query()
                ->where('student_id', $student->id)
                ->where('school_id', $school->id)
                ->where('is_current', true)
                ->whereNull('left_at')
                ->update([
                    'is_current' => false,
                    'left_at' => now(),
                    'notes' => \DB::raw("CONCAT(COALESCE(notes,''), '\nRemoved via StudentPlacementController')"),
                ]);

            if ($request->wantsJson()) {
                return response()->json(['message' => 'Placement removed.', 'closed' => $updated]);
            }

            return back()->with('success', 'Student removed from class section.');
        } catch (\Exception $e) {
            Log::error('Failed to remove student placement', [
                'student_id' => $student->id,
                'error' => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => 'Unable to remove placement.']);
        }
    }
}

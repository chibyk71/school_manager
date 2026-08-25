<?php

namespace App\Http\Controllers\Student;

use App\Models\Academic\Student;
use App\Models\Guardian;
use App\Services\Student\StudentGuardianService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * StudentGuardianController – Manage Guardian Relationships for Students
 *
 * Handles attaching, detaching, and updating guardians on a student record.
 * Guardian data lives on the Guardian model and links to Student via the
 * guardian_student pivot table (which carries relationship_type, is_primary,
 * can_pickup, emergency_contact fields).
 *
 * ── Authorization ─────────────────────────────────────────────────────────────
 * Uses StudentPolicy::manageGuardians() → requires `students.manage-guardians`.
 * Admin and admissions staff can manage guardians; teachers cannot.
 *
 * ── Business Logic ────────────────────────────────────────────────────────────
 * StudentGuardianService handles:
 *   - Pivot data validation (only one primary guardian per student)
 *   - Enforcing guardian school scoping (can't attach a guardian from another school)
 *   - Firing GuardianAddedToStudent / GuardianRemovedFromStudent events
 *
 * ── Fits into the Student Management Module ──────────────────────────────────
 * - Routes:
 *     POST   /students/{student}/guardians            → attach
 *     PATCH  /students/{student}/guardians/{guardian} → update pivot
 *     DELETE /students/{student}/guardians/{guardian} → detach
 * - Frontend: Students/Show.vue guardians tab
 * - Policy: StudentPolicy::manageGuardians()
 */
class StudentGuardianController
{
    public function __construct(
        protected StudentGuardianService $guardianService
    ) {
    }

    /**
     * Display all guardians for a student.
     * GET /admin/students/{student}/guardians
     */
    public function index(Student $student)
    {
            Gate::authorize('manageGuardians', $student);
    
            $student->load(['guardians.profile', 'guardians.school']);
    
            return Inertia::render('Students/Partials/Guardians/Index', [
                'student' => new \App\Http\Resources\Student\StudentResource($student),
                'guardians' => $student->guardians->map(function ($guardian) {
                    return [
                        'id' => $guardian->id,
                        'full_name' => $guardian->full_name,
                        'photo_url' => $guardian->photo_url,
                        'phone' => $guardian->phone,
                        'email' => $guardian->email,
                        'relationship' => $guardian->pivot->relationship,
                        'is_primary_contact' => (bool) $guardian->pivot->is_primary_contact,
                        'can_pickup' => (bool) $guardian->pivot->can_pickup,
                        'can_access_portal' => (bool) $guardian->pivot->can_access_portal,
                        'is_emergency_contact' => (bool) $guardian->pivot->is_emergency_contact,
                        'emergency_contact_priority' => $guardian->pivot->emergency_contact_priority,
                        'notes' => $guardian->pivot->notes,
                    ];
                }),
            ]);

        $student->load(['guardians.profile', 'guardians.school']);

        return Inertia::render('Students/Partials/Guardians/Index', [
            'student' => new \App\Http\Resources\Student\StudentResource($student),
            'guardians' => $student->guardians->map(function ($guardian) {
                return [
                    'id' => $guardian->id,
                    'full_name' => $guardian->full_name,
                    'photo_url' => $guardian->photo_url,
                    'phone' => $guardian->phone,
                    'email' => $guardian->email,
                    'relationship' => $guardian->pivot->relationship,
                    'is_primary_contact' => (bool) $guardian->pivot->is_primary_contact,
                    'can_pickup' => (bool) $guardian->pivot->can_pickup,
                    'can_access_portal' => (bool) $guardian->pivot->can_access_portal,
                    'is_emergency_contact' => (bool) $guardian->pivot->is_emergency_contact,
                    'emergency_contact_priority' => $guardian->pivot->emergency_contact_priority,
                    'notes' => $guardian->pivot->notes,
                ];
            }),
        ]);
    }

    /**
     * Attach a guardian to a student.
     * POST /students/{student}/guardians
     *
     * Expected payload:
     *   guardian_id:         UUID of an existing Guardian record
     *   relationship_type:   father | mother | guardian | sibling | other
     *   is_primary:          bool (true = primary contact)
     *   can_pickup:          bool
     *   emergency_contact:   bool
     */
    public function store(Request $request, Student $student)
    {
        Gate::authorize('manageGuardians', $student);

        $request->validate([
            'guardian_id' => ['required', 'uuid', 'exists:guardians,id'],
            'relationship_type' => ['required', 'string', 'in:father,mother,guardian,sibling,other'],
            'is_primary' => ['boolean'],
            'can_pickup' => ['boolean'],
            'emergency_contact' => ['boolean'],
        ]);

        try {
            $this->guardianService->attach(
                student: $student,
                guardianId: $request->string('guardian_id'),
                relationshipType: $request->string('relationship_type'),
                isPrimary: $request->boolean('is_primary', false),
                canPickup: $request->boolean('can_pickup', false),
                emergencyContact: $request->boolean('emergency_contact', false),
            );

            if ($request->wantsJson()) {
                return response()->json(['message' => 'Guardian added to student.'], 201);
            }

            return back()->with('success', 'Guardian linked to student successfully.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());

        } catch (\Exception $e) {
            Log::error('Failed to attach guardian to student', [
                'student_id' => $student->id,
                'guardian_id' => $request->input('guardian_id'),
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Update pivot data for an existing student-guardian relationship.
     * PATCH /students/{student}/guardians/{guardian}
     *
     * Allows updating is_primary, can_pickup, emergency_contact, relationship_type.
     */
    public function update(Request $request, Student $student, Guardian $guardian)
    {
        Gate::authorize('manageGuardians', $student);

        $request->validate([
            'relationship_type' => ['sometimes', 'string', 'in:father,mother,guardian,sibling,other'],
            'is_primary' => ['sometimes', 'boolean'],
            'can_pickup' => ['sometimes', 'boolean'],
            'emergency_contact' => ['sometimes', 'boolean'],
        ]);

        try {
            $this->guardianService->updatePivot(
                student: $student,
                guardian: $guardian,
                data: $request->only(['relationship_type', 'is_primary', 'can_pickup', 'emergency_contact']),
            );

            if ($request->wantsJson()) {
                return response()->json(['message' => 'Guardian relationship updated.']);
            }

            return back()->with('success', 'Guardian relationship updated.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());

        } catch (\Exception $e) {
            Log::error('Failed to update student-guardian pivot', [
                'student_id' => $student->id,
                'guardian_id' => $guardian->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Unable to update guardian relationship.']);
        }
    }

    /**
     * Detach a guardian from a student.
     * DELETE /students/{student}/guardians/{guardian}
     *
     * Does not delete the Guardian record — only removes the link.
     */
    public function destroy(Request $request, Student $student, Guardian $guardian)
    {
        Gate::authorize('manageGuardians', $student);

        try {
            $this->guardianService->detach($student, $guardian);

            if ($request->wantsJson()) {
                return response()->json(['message' => 'Guardian removed from student.']);
            }

            return back()->with('success', 'Guardian unlinked from student.');

        } catch (\Exception $e) {
            Log::error('Failed to detach guardian from student', [
                'student_id' => $student->id,
                'guardian_id' => $guardian->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Unable to remove guardian.']);
        }
    }
}

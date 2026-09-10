<?php

namespace App\Services\Student\Concerns;

use App\Models\School;
use App\Models\Student\Student;
use App\Models\Student\StudentSessionPlacement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Canonical close path for current placements (Phase 8).
 */
trait ClosesCurrentPlacement
{
    public function closeCurrentPlacement(
        Student $student,
        School $school,
        ?User $actor = null,
        array $options = []
    ): int {
        return (int) DB::transaction(function () use ($student, $school, $actor, $options) {
            $this->assertStudentSchool($student, $school);

            $sessionId = $options['academic_session_id'] ?? null;
            if ($sessionId) {
                $this->assertAcademicSessionBelongsToSchool($sessionId, $school);
            }

            $query = StudentSessionPlacement::query()
                ->where('student_id', $student->id)
                ->where('school_id', $school->id)
                ->where('is_current', true)
                ->whereNull('left_at')
                ->lockForUpdate();

            if ($sessionId) {
                $query->where('academic_session_id', $sessionId);
            }

            $placements = $query->get();
            if ($placements->isEmpty()) {
                throw ValidationException::withMessages([
                    'placement' => 'Student has no current placement to close.',
                ]);
            }

            $note = $options['notes'] ?? 'Placement closed';
            $actorId = $actor?->id;
            $closed = 0;

            foreach ($placements as $placement) {
                $existingNotes = trim((string) ($placement->notes ?? ''));
                $placement->update([
                    'is_current' => false,
                    'left_at' => now(),
                    'notes' => $existingNotes === '' ? $note : ($existingNotes."\n".$note),
                    'meta' => array_merge($placement->meta ?? [], array_filter([
                        'closed_at' => now()->toIso8601String(),
                        'closed_by' => $actorId,
                        'close_reason' => $options['reason'] ?? 'manual_close',
                    ])),
                ]);
                $closed++;
            }

            Log::info('Student current placement closed', [
                'student_id' => $student->id,
                'school_id' => $school->id,
                'closed' => $closed,
                'actor_id' => $actorId,
            ]);

            return $closed;
        });
    }
}

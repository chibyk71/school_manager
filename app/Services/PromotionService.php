<?php
// app/Services/PromotionService.php

namespace App\Services;

use App\Events\AcademicSessionCompleted;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\ClassLevel;
use App\Models\Academic\ClassSection;
use App\Models\Academic\Student;
use App\Models\Promotion\PromotionBatch;
use App\Models\Promotion\PromotionStudent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PromotionService
 *
 * Central business logic for end-of-session student promotion.
 * Called automatically when the last term of a session is closed.
 *
 * @package App\Services
 */
class PromotionService
{
    /**
     * Main entry point: Create a promotion batch for a completed academic session.
     *
     * @param AcademicSession $session The session that just ended
     * @return PromotionBatch
     * @throws \Exception
     */
    public function createPromotionBatchForSession(AcademicSession $session): PromotionBatch
    {
        $school = $session->school ?? GetSchoolModel();

        if (!$school) {
            throw new \Exception('No school context found for promotion batch creation.');
        }

        return DB::transaction(function () use ($session, $school) {
            // 1. Create the batch
            $batch = PromotionBatch::create([
                'academic_session_id' => $session->id,
                'school_id' => $school->id,
                'name' => "{$session->name} Promotion",
                'description' => "Automatic promotion batch for completed session {$session->name}",
                'status' => 'pending',
                'total_students' => 0,
                'processed_students' => 0,
            ]);

            Log::info("Promotion batch created", ['batch_id' => $batch->id, 'session' => $session->name]);

            // 2. Get all students in this session
            $students = Student::whereHas('classSections', function ($q) use ($session) {
                $q->wherePivot('academic_session_id', $session->id);
            })->with([
                'currentClassSection.classLevel',
                'user' // needed for name in logs/reports
            ])->get();

            $total = $students->count();
            $batch->update(['total_students' => $total]);

            if ($total === 0) {
                $batch->update(['status' => 'completed']);
                Log::info("No students found for promotion in session", ['session_id' => $session->id]);
                return $batch;
            }

            // 3. Process each student
            $records = [];
            foreach ($students as $student) {
                $record = $this->calculateStudentPromotion($student, $session);
                $record['promotion_batch_id'] = $batch->id;
                $record['student_id'] = $student->id;

                $records[] = $record;
            }

            // Bulk insert for performance
            PromotionStudent::insert($records);

            Log::info("Promotion batch populated", [
                'batch_id' => $batch->id,
                'students' => $total
            ]);

            // 4. Fire event for notifications
            event(new AcademicSessionCompleted($batch));

            return $batch->fresh();
        });
    }

    /**
     * Calculate promotion recommendation for a single student.
     *
     * @param Student $student
     * @param AcademicSession $session
     * @return array
     */
    protected function calculateStudentPromotion(Student $student, AcademicSession $session): array
    {
        $currentSection = $student->currentClassSection();
        $currentLevel = $currentSection?->classLevel;

        // Default values
        $recommendation = 'promote';
        $failedSubjects = 0;
        $averageScore = null;
        $nextSectionId = null;

        if (!$currentSection || !$currentLevel) {
            $recommendation = 'repeat'; // safety
        } else {
            // ────────────────────────────────────────────────────────
            // TODO: Replace this placeholder with real result logic
            // ────────────────────────────────────────────────────────
            // Example: Get CA + Exam results from your Result model
            // $results = $student->results()->where('academic_session_id', $session->id)->get();
            // $failedSubjects = $results->where('grade', 'F')->count();
            // $averageScore = $results->avg('total_score');

            // Placeholder logic (you will replace this)
            $failedSubjects = rand(0, 4); // ← REMOVE THIS IN PRODUCTION
            $averageScore = rand(35, 95); // ← REMOVE THIS IN PRODUCTION

            // Real logic will go here later
            if ($failedSubjects >= 3) {
                $recommendation = 'repeat';
            } elseif ($failedSubjects >= 1) {
                $recommendation = 'probation';
            }

            // Find next class section (same arm, next level)
            if ($recommendation === 'promote' || $recommendation === 'probation') {
                $nextLevel = $this->getNextClassLevel($currentLevel);
                if ($nextLevel) {
                    $nextSection = ClassSection::where('class_level_id', $nextLevel->id)
                        ->where('name', $currentSection->name) // same arm: A → A
                        ->first();

                    $nextSectionId = $nextSection?->id;
                }

                // If graduating (e.g., SSS3), set to 'graduated'
                if (!$nextLevel) {
                    $recommendation = 'graduated';
                }
            }
        }

        return [
            'current_class_section_id' => $currentSection?->id,
            'next_class_section_id' => $nextSectionId,
            'recommendation' => $recommendation,
            'final_decision' => null,
            'failed_subjects_count' => $failedSubjects,
            'average_score' => $averageScore,
            'is_processed' => false,
        ];
    }

    /**
     * Get the next class level (e.g., JSS1 → JSS2).
     *
     * @param ClassLevel $currentLevel
     * @return ClassLevel|null
     */
    protected function getNextClassLevel(ClassLevel $currentLevel): ?ClassLevel
    {
        // Assuming levels are ordered by name or have a 'sequence' column
        // Option 1: If you have a `sequence` or `order` column:
        // return ClassLevel::where('school_id', $currentLevel->school_id)
        //     ->where('sequence', $currentLevel->sequence + 1)
        //     ->first();

        // Option 2: Simple name-based (common in Nigeria)
        $patterns = [
            'JSS1' => 'JSS2', 'JSS2' => 'JSS3', 'JSS3' => 'SSS1',
            'SSS1' => 'SSS2', 'SSS2' => 'SSS3', 'SSS3' => null,
            'Primary 1' => 'Primary 2', /* ... etc */
        ];

        $nextName = $patterns[$currentLevel->name] ?? $patterns[$currentLevel->display_name] ?? null;

        if (!$nextName) return null;

        return ClassLevel::where('school_id', $currentLevel->school_id)
            ->where(function ($q) use ($nextName) {
                $q->where('name', $nextName)
                  ->orWhere('display_name', $nextName);
            })
            ->first();
    }

    /**
     * Execute an approved promotion batch (called from controller/job).
     *
     * @param PromotionBatch $batch
     * @return void
     */
    public function executeApprovedBatch(PromotionBatch $batch): void
    {
        if ($batch->status !== 'approved') {
            throw new \Exception('Only approved batches can be executed.');
        }

        $batch->update(['status' => 'executing']);

        // This will be queued via Laravel Bus + Horizon
        // See next step: ProcessStudentPromotion job
        \App\Jobs\ProcessStudentPromotion::dispatch($batch);
    }
}
<?php

namespace App\Http\Resources\Exam;

use App\Models\Exam\Exam;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ExamResource
 *
 * Shapes Exam model data for both Inertia page props and JSON API responses.
 *
 * Design decisions:
 * - Computed flag `is_editable` and `is_locked` are always included so the frontend
 *   never needs to replicate the status machine logic — it simply reads these flags.
 * - Relations are conditionally included using whenLoaded() — the resource never
 *   triggers extra queries. Controllers eager-load what they need before passing to resource.
 * - `score_entry_progress` is included as a float (0–100) computed from the model accessor.
 * - Template components are included in a flat, sorted format ready for the score-entry form.
 * - `status_label` and `status_severity` are derived from the EXAM_STATUS_CONFIG equivalent
 *   on the backend, so the frontend tag/badge component can render directly without a lookup map.
 *
 * Fits into the module:
 * - ExamController::index()  → ExamResource::collection($result['data'])
 * - ExamController::show()   → new ExamResource($exam)
 * - ExamController::store()  → new ExamResource($exam)
 * - ExamController::update() → new ExamResource($exam)
 */
class ExamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Exam $this */

        $statusMeta = $this->resolveStatusMeta();

        return [
            // Identity
            'id'   => $this->id,
            'name' => $this->name,
            'description' => $this->description,

            // Status machine
            'status'          => $this->status,
            'status_label'    => $statusMeta['label'],
            'status_severity' => $statusMeta['severity'],
            'status_icon'     => $statusMeta['icon'],

            // Academic context
            'academic_session_id' => $this->academic_session_id,
            'session_name'        => $this->whenLoaded('academicSession', fn() => $this->academicSession->name),

            'term_id'   => $this->term_id,
            'term_name' => $this->whenLoaded('term', fn() => $this->term?->name),

            // Class scope
            'class_level_id'   => $this->class_level_id,
            'level_name'       => $this->whenLoaded('classLevel', fn() => $this->classLevel?->name),
            'class_section_id' => $this->class_section_id,
            'section_name'     => $this->whenLoaded('classSection', fn() => $this->classSection?->display_name),

            // Assessment template
            'assessment_template_id' => $this->assessment_template_id,
            'template_name'          => $this->whenLoaded('assessmentTemplate', fn() => $this->assessmentTemplate->name),
            'template'               => $this->whenLoaded('assessmentTemplate', fn() => [
                'id'           => $this->assessmentTemplate->id,
                'name'         => $this->assessmentTemplate->name,
                'components'   => $this->assessmentTemplate->sorted_components,
                'total_score'  => $this->assessmentTemplate->total_score,
                'pass_mark'    => $this->assessmentTemplate->pass_mark,
            ]),

            // Dates
            'exam_start_date'       => $this->exam_start_date?->format('Y-m-d'),
            'exam_end_date'         => $this->exam_end_date?->format('Y-m-d'),
            'published_at'          => $this->published_at?->toISOString(),
            'results_published_at'  => $this->results_published_at?->toISOString(),
            'locked_at'             => $this->locked_at?->toISOString(),

            // Computed flags — frontend reads these instead of re-implementing the logic
            'is_editable'           => $this->isEditable(),
            'is_locked'             => $this->isLocked(),
            'can_compute_results'   => in_array($this->status, [
                \App\Models\Exam\Exam::STATUS_COMPLETED,
                Exam::STATUS_ONGOING,
            ], true) && !$this->isLocked(),
            'can_approve_results'   => $this->isCompleted(),

            // Progress
            'score_entry_progress' => $this->score_entry_progress,

            // Timetable (only when explicitly loaded)
            'timetable' => $this->whenLoaded('timetable', fn() =>
                $this->timetable->map(fn($entry) => [
                    'id'         => $entry->id,
                    'subject_id' => $entry->subject_id,
                    'subject'    => $entry->subject?->only('id', 'name', 'code'),
                    'exam_date'  => $entry->exam_date?->format('Y-m-d'),
                    'start_time' => $entry->start_time,
                    'end_time'   => $entry->end_time,
                    'venue'      => $entry->venue,
                ])
            ),

            // Audit
            'created_by' => $this->whenLoaded('createdBy', fn() => $this->createdBy?->name),
            'approved_by'=> $this->whenLoaded('approvedBy', fn() => $this->approvedBy?->name),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }

    private function resolveStatusMeta(): array
    {
        return match ($this->status) {
            Exam::STATUS_DRAFT            => ['label' => 'Draft',            'severity' => 'secondary', 'icon' => 'pi pi-pencil'],
            Exam::STATUS_PUBLISHED        => ['label' => 'Published',        'severity' => 'info',      'icon' => 'pi pi-eye'],
            Exam::STATUS_ONGOING          => ['label' => 'Ongoing',          'severity' => 'warn',      'icon' => 'pi pi-play'],
            Exam::STATUS_COMPLETED        => ['label' => 'Completed',        'severity' => 'success',   'icon' => 'pi pi-check'],
            Exam::STATUS_RESULTS_APPROVED => ['label' => 'Results Approved', 'severity' => 'success',   'icon' => 'pi pi-lock'],
            default                       => ['label' => $this->status,      'severity' => 'secondary', 'icon' => 'pi pi-question'],
        };
    }
}

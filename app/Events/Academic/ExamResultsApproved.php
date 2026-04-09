<?php

namespace App\Events\Academic;

use App\Models\Exam\Exam;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ExamResultsApproved
 *
 * Fired when an exam's results are approved and locked (status → results_approved).
 * This is the final lifecycle event for an exam.
 *
 * Typical listeners:
 * - Notify parents / students that report cards are available
 * - Trigger the PromotionService to evaluate student promotions
 * - Update the student's academic history record
 * - Send a summary report to school admin
 * - Log to audit trail
 *
 * The approved_by_user_id identifies who clicked "Approve Results".
 * The affected_student_ids list allows listeners to batch-notify efficiently
 * rather than re-querying computed_results.
 */
class ExamResultsApproved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Exam $exam,
        public readonly string $approvedByUserId,
        /** @var string[] IDs of all students whose results were approved */
        public readonly array $affectedStudentIds = [],
    ) {}
}

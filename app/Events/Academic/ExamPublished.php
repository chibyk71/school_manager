<?php

namespace App\Events\Academic;

use App\Models\Exam\Exam;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ExamPublished
 *
 * Fired when an exam transitions from 'draft' to 'published'.
 *
 * Typical listeners:
 * - Send notification to class teachers informing them score entry is open
 * - Log the publication in the activity log
 * - Push a dashboard notification to the school admin
 *
 * The full Exam model is serialized into the queue job.
 * Listeners can access $event->exam to read all exam properties.
 */
class ExamPublished
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Exam $exam,
        public readonly string $publishedByUserId,
    ) {}
}

<?php
// app/Events/AcademicSessionCompleted.php

namespace App\Events;

use App\Models\Promotion\PromotionBatch;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an Academic Session has ended (all terms closed).
 * Triggers creation of Promotion Batch.
 */
class AcademicSessionCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The completed promotion batch (created by service).
     *
     * @var PromotionBatch
     */
    public PromotionBatch $batch;

    /**
     * Create a new event instance.
     *
     * @param PromotionBatch $batch
     * @return void
     */
    public function __construct(PromotionBatch $batch)
    {
        $this->batch = $batch;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('school.' . $this->batch->school_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'session.completed';
    }
}
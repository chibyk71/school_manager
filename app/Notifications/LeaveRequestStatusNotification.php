<?php

namespace App\Notifications;

use App\Models\Employee\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification for leave request status updates (approved or rejected).
 */
class LeaveRequestStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The leave request instance.
     *
     * @var LeaveRequest
     */
    protected $leaveRequest;

    /**
     * The status of the leave request (approved or rejected).
     *
     * @var string
     */
    protected $status;

    /**
     * The reason for rejection, if applicable.
     *
     * @var string|null
     */
    protected $rejectedReason;

    /**
     * Create a new notification instance.
     *
     * @param LeaveRequest $leaveRequest
     * @param string $status
     * @param string|null $rejectedReason
     */
    public function __construct(LeaveRequest $leaveRequest, string $status, ?string $rejectedReason = null)
    {
        $this->leaveRequest = $leaveRequest;
        $this->status = $status;
        $this->rejectedReason = $rejectedReason;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array<string>
     */
    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return MailMessage
     */
    public function toMail($notifiable)
    {
        $subject = "Leave Request {$this->status} - {$this->leaveRequest->leaveType->name}";
        $message = (new MailMessage)
            ->subject($subject)
            ->line("Your leave request for {$this->leaveRequest->leaveType->name} from {$this->leaveRequest->start_date->format('Y-m-d')} to {$this->leaveRequest->end_date->format('Y-m-d')} has been {$this->status}.")
            ->line("Reason: {$this->leaveRequest->reason}");

        if ($this->status === 'rejected' && $this->rejectedReason) {
            $message->line("Rejection Reason: {$this->rejectedReason}");
        }

        return $message->action('View Leave Request', route('leave-requests.show', $this->leaveRequest->id));
    }

    /**
     * Get the database representation of the notification.
     *
     * @param mixed $notifiable
     * @return DatabaseMessage
     */
    public function toDatabase($notifiable)
    {
        return new DatabaseMessage([
            'type' => 'leave_request_status',
            'message' => "Your leave request for {$this->leaveRequest->leaveType->name} has been {$this->status}.",
            'data' => [
                'leave_request_id' => $this->leaveRequest->id,
                'status' => $this->status,
                'rejected_reason' => $this->rejectedReason,
                'leave_type' => $this->leaveRequest->leaveType->name,
                'start_date' => $this->leaveRequest->start_date->format('Y-m-d'),
                'end_date' => $this->leaveRequest->end_date->format('Y-m-d'),
            ],
        ]);
    }
}
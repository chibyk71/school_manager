<?php

namespace App\Notifications;

use App\Models\CustomField;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GlobalCustomFieldUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public CustomField $field;
    public string $action; // 'created' or 'updated' — makes message more precise

    /**
     * Create a new notification instance.
     */
    public function __construct(CustomField $field, string $action = 'updated')
    {
        $this->field  = $field;
        $this->action = in_array($action, ['created', 'updated']) ? $action : 'updated';
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database']; // Add 'broadcast' later if you want real-time bell alerts
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $modelName   = class_basename($this->field->model_type);
        $fieldLabel  = $this->field->label ?? $this->field->name;
        $actionWord  = ucfirst($this->action); // Created / Updated

        return (new MailMessage)
            ->subject("Global Field {$actionWord}: {$fieldLabel} ({$modelName})")
            ->greeting("Hello {$notifiable->name},")
            ->line("A **global default field** has been **{$this->action}** in the system.")
            ->line("**Field**: {$fieldLabel} ({$this->field->name})")
            ->line("**Applies to**: {$modelName} records")
            ->line("Your school already has a **local override** for this field.")
            ->line("You may want to review your version and decide whether to keep it or adopt the new global default.")
            ->action('Review & Manage Overrides', url('/settings/custom-fields?resource=' . strtolower($modelName)))
            ->line('This is an automated notification from your school management system.')
            ->salutation('Best regards, The SchoolSync Team');
    }

    /**
     * Get the array representation of the notification (database storage).
     */
    public function toArray(object $notifiable): array
    {
        return [
            'field_id'       => $this->field->id,
            'field_name'     => $this->field->name,
            'field_label'    => $this->field->label ?? $this->field->name,
            'model_type'     => $this->field->model_type,
            'action'         => $this->action,
            'message'        => "Global field '{$this->field->label}' was {$this->action}. You have an override.",
            'action_url'     => url('/settings/custom-fields?resource=' . strtolower(class_basename($this->field->model_type))),
            'created_at'     => now()->toDateTimeString(),
        ];
    }
}

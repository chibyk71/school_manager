<?php

namespace App\Events;

use App\Models\CustomField;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event: CustomFieldGlobalUpdated
 *
 * Fired whenever a **global** custom field (school_id = null) is created or updated.
 *
 * Purpose:
 *   - Trigger notifications to schools that have overrides for this field
 *   - Allow decoupling: listener can send email, database notification, Slack, etc.
 *   - Queueable in production for better performance
 *
 * Usage:
 *   event(new CustomFieldGlobalUpdated($field));
 *   // or CustomFieldGlobalUpdated::dispatch($field);
 */
class CustomFieldGlobalUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public CustomField $field;

    /**
     * Create a new event instance.
     */
    public function __construct(CustomField $field)
    {
        $this->field = $field;

        // Safety: only global fields should trigger this
        if (!is_null($field->school_id)) {
            throw new \InvalidArgumentException('This event is only for global fields (school_id = null)');
        }
    }
}

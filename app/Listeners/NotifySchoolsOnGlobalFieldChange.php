<?php

namespace App\Listeners;

use App\Events\CustomFieldGlobalUpdated;
use App\Models\CustomField;
use App\Models\School;
use App\Notifications\GlobalCustomFieldUpdatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Listener: NotifySchoolsOnGlobalFieldChange
 *
 * When a global field is updated/created:
 *   1. Finds all schools that have an override for the same field name + model_type
 *   2. Sends a notification to each school's admins
 *
 * Queueable → can be processed asynchronously in production
 * (configure in config/queue.php or via ShouldQueue interface)
 */
class NotifySchoolsOnGlobalFieldChange implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(CustomFieldGlobalUpdated $event): void
    {
        $globalField = $event->field;

        // Find schools that already overrode this field
        $conflictingSchools = School::whereHas('customFields', function ($query) use ($globalField) {
            $query->where('name', $globalField->name)
                ->where('model_type', $globalField->model_type)
                ->whereNotNull('school_id');
        })->get();

        if ($conflictingSchools->isEmpty()) {
            return; // no conflicts — nothing to notify
        }

        $notification = new GlobalCustomFieldUpdatedNotification($globalField);

        foreach ($conflictingSchools as $school) {
            // Get admins of this school (adjust query to your User/Role setup)
            $admins = $school->users()
                ->whereHas('roles', fn($q) => $q->where('name', 'admin'))
                ->get();

            if ($admins->isNotEmpty()) {
                try {
                    $admins->each->notify($notification);
                } catch (\Exception $e) {
                    Log::error('Failed to notify school admins about global field change', [
                        'school_id' => $school->id,
                        'field_id' => $globalField->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Log::info("Notified schools about updated global custom field", [
            'field_id' => $globalField->id,
            'field_name' => $globalField->name,
            'model_type' => $globalField->model_type,
            'school_count' => $conflictingSchools->count(),
        ]);
    }
}

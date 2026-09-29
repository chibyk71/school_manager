<?php

namespace App\Listeners\SchoolSection;

use App\Events\SchoolSection\SchoolSectionRestored;
use Illuminate\Support\Facades\Log;

/**
 * SyncLaratrustTeamOnRestore
 *
 * Historical name retained for event wiring stability.
 *
 * As of Permission Phase 1, SchoolSection is no longer the Laratrust Team.
 * Laratrust team scope is School (school_id on role_user / permission_user).
 * Restoring a section therefore has no Laratrust pivot integrity to re-sync.
 *
 * This listener now only logs the restore for operational visibility.
 * It performs no pivot writes or authorization mutations.
 *
 * @see App\Events\SchoolSection\SchoolSectionRestored
 */
class SyncLaratrustTeamOnRestore implements \Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit
{
    public function handle(SchoolSectionRestored $event): void
    {
        $sections = $event->sections;

        if ($sections->isEmpty()) {
            return;
        }

        foreach ($sections as $section) {
            Log::info('SchoolSection restored (no Laratrust team sync — team scope is School)', [
                'section_id'   => $section->id,
                'section_name' => $section->name,
                'school_id'    => $section->school_id,
            ]);
        }
    }
}

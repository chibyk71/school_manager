<?php

namespace App\Listeners\SchoolSection;

use App\Events\SchoolSection\SchoolSectionRestored;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SyncLaratrustTeamOnRestore
 *
 * Verifies and logs Laratrust role assignment integrity after one or more
 * sections are restored from soft-delete.
 *
 * ── Why This Listener Exists ─────────────────────────────────────────────
 * SchoolSection IS the Laratrust Team model (config/laratrust.php maps
 * 'team' => SchoolSection). When a section is restored, the section row
 * itself becomes active again — no separate "team record" needs to be
 * created because the section row is the team record.
 *
 * However, role assignments (role_user pivot rows with school_section_id)
 * that existed before the section was deleted are still in the database —
 * soft-delete does not cascade to pivot tables. This means:
 *   - If assignments exist: roles are immediately intact on restore. ✓
 *   - If assignments are missing: admins need to manually reassign roles.
 *
 * This listener logs which restored sections have existing assignments
 * (intact) vs zero assignments (need attention), giving admins visibility
 * without making any destructive changes.
 *
 * ── Why Not Actively Re-Sync ─────────────────────────────────────────────
 * Actively deleting or reassigning pivot rows would be destructive.
 * If an admin restores a section, they almost certainly want the original
 * role assignments back — deleting and recreating them could lose
 * scoped assignments that were manually configured. A log entry is
 * the safe, auditable, non-destructive choice.
 *
 * ── Synchronous ──────────────────────────────────────────────────────────
 * Not queued — a DB count query and a log write are instant operations.
 * Queuing this would add latency to the restore feedback without benefit.
 *
 * ── ShouldHandleEventsAfterCommit ────────────────────────────────────────
 * Runs after the restore transaction commits, ensuring the pivot check
 * queries see the restored section rows.
 *
 * @see App\Events\SchoolSection\SchoolSectionRestored
 */
class SyncLaratrustTeamOnRestore implements \Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit
{
    /**
     * Handle the SchoolSectionRestored event.
     *
     * @param  SchoolSectionRestored  $event
     * @return void
     */
    public function handle(SchoolSectionRestored $event): void
    {
        $sections = $event->sections;

        if ($sections->isEmpty()) {
            return;
        }

        // The Laratrust team foreign key column on role_user pivot.
        // Resolved from config to stay in sync if the key name changes.
        $teamForeignKey = config('laratrust.foreign_keys.team', 'school_section_id');

        foreach ($sections as $section) {
            try {
                // Count role assignments scoped to this section in the pivot table.
                $assignmentCount = DB::table(config('laratrust.tables.role_user', 'role_user'))
                    ->where($teamForeignKey, $section->id)
                    ->count();

                if ($assignmentCount > 0) {
                    // Assignments intact — section is immediately usable as a team.
                    Log::info('SyncLaratrustTeamOnRestore: role assignments intact after restore', [
                        'section_id'       => $section->id,
                        'section_name'     => $section->name,
                        'school_id'        => $section->school_id,
                        'assignment_count' => $assignmentCount,
                        'status'           => 'intact',
                    ]);
                } else {
                    // No assignments found — admins should manually reassign roles
                    // to this section if scoped role access is needed.
                    Log::warning('SyncLaratrustTeamOnRestore: no role assignments found after restore', [
                        'section_id'   => $section->id,
                        'section_name' => $section->name,
                        'school_id'    => $section->school_id,
                        'status'       => 'needs_attention',
                        'action'       => 'Manually reassign roles to this section if scoped access is required.',
                    ]);
                }
            } catch (\Throwable $e) {
                // Log but do not re-throw — a logging failure should not
                // roll back a successfully completed restore operation.
                Log::error('SyncLaratrustTeamOnRestore: failed to check pivot integrity', [
                    'section_id' => $section->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }
    }
}

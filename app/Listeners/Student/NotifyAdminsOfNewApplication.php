<?php

namespace App\Listeners\Student;

use App\Events\Student\ApplicationSubmitted;
use App\Models\User;
use App\Notifications\Student\NewApplicationSubmitted;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * NotifyAdminsOfNewApplication Listener (v2.0 – Production-Ready)
 *
 * This listener is triggered whenever a new student application is submitted 
 * (either via public portal or by an admin).
 *
 * It finds all users who have permission to review applications (typically admins 
 * and admissions staff) and sends them the NewApplicationSubmitted notification.
 *
 * Features / Problems Solved:
 * - Automatically notifies the right people when a new application arrives.
 * - Respects permissions instead of hardcoding roles (flexible for different schools).
 * - Queuable to prevent delaying the main application submission process.
 * - Uses the existing NewApplicationSubmitted notification for consistent messaging.
 *
 * Fits into the Student Management Module:
 * - Listens to ApplicationSubmitted event (dispatched from StudentApplicationService).
 * - Works with the permission system (e.g., 'applications.view' or 'applications.manage').
 * - Helps keep admissions team informed in real time.
 */

class NotifyAdminsOfNewApplication implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(ApplicationSubmitted $event): void
    {
        // Find users who can manage/review applications in this school
        $admins = User::whereHas('roles.permissions', function ($query) {
            $query->where('name', 'applications.view')
                ->orWhere('name', 'applications.manage');
        })
            ->orWhereHas('permissions', function ($query) {
                $query->where('name', 'applications.view')
                    ->orWhere('name', 'applications.manage');
            })
            ->where(function ($query) use ($event) {
                // School-specific or tenant-wide admins
                $query->whereNull('school_id') // tenant-wide admins
                    ->orWhere('school_id', $event->school->id);
            })
            ->get();

        $admins->each(function (User $admin) use ($event) {
            $admin->notify(
                new NewApplicationSubmitted($event)
            );
        });

        // Optional: Also notify specific "Admissions Officer" role if you have one
        // $admissionsOfficers = User::role('admissions-officer')
        //     ->where('school_id', $event->school->id)
        //     ->get();
    }

    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(ApplicationSubmitted $event): bool
    {
        // Always queue to avoid slowing down the application submission process
        return true;
    }
}
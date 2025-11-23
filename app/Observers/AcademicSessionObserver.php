<?php

namespace App\Observers;

use App\Models\Academic\AcademicSession;
use App\Models\Academic\Term;

class AcademicSessionObserver
{
    /**
     * Handle the AcademicSession "created" event.
     */
    public function created(AcademicSession $academicSession): void
    {
        $names = ['First Term', 'Second Term', 'Third Term'];
        $start = $academicSession->start_date;
        $interval = $academicSession->end_date->diffInMonths($academicSession->start_date) / 3;

        foreach ($names as $i => $name) {
            Term::create([
                'academic_session_id' => $academicSession->id,
                'name' => $name,
                'start_date' => $start->copy(),
                'end_date' => $start->copy()->addMonths($interval),
                'status' => 'pending',
                'school_id' => $academicSession->school_id,
            ]);
            $start = $start->copy()->addMonths($interval);
        }
    }

    /**
     * Handle the AcademicSession "updated" event.
     */
    public function updated(AcademicSession $academicSession): void
    {
        //
    }

    /**
     * Handle the AcademicSession "deleted" event.
     */
    public function deleted(AcademicSession $academicSession): void
    {
        //
    }

    /**
     * Handle the AcademicSession "restored" event.
     */
    public function restored(AcademicSession $academicSession): void
    {
        //
    }

    /**
     * Handle the AcademicSession "force deleted" event.
     */
    public function forceDeleted(AcademicSession $academicSession): void
    {
        //
    }
}

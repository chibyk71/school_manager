<?php

namespace App\Listeners;

use App\Events\FeeAssignedToClasses;
use App\Models\Finance\FeeAssignment;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

class GenerateFeeAssignments implements ShouldQueue
{
    use InteractsWithQueue;

    public $queue = 'finance';

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(FeeAssignedToClasses $event): void
    {
        $fee = $event->fee->load('feeType', 'term');
        $classSectionIds = $event->classSectionIds;

        $students = User::whereHas('studentRecord', function ($q) use ($classSectionIds) {
            $q->whereIn('class_section_id', $classSectionIds);
        })
            ->where('school_id', $fee->school_id)
            ->select('id', 'name')
            ->get();

        DB::transaction(function () use ($fee, $students, $event) {
            foreach ($students as $student) {
                $existing = FeeAssignment::where([
                    'fee_id' => $fee->id,
                    'user_id' => $student->id,
                    'term_id' => $fee->term_id,
                ])->withTrashed();

                if ($event->isUpdate) {
                    $existing->restore(); // in case soft-deleted
                }

                $concessionAmount = $this->calculateConcession($student, $fee);

                FeeAssignment::updateOrCreate(
                    [
                        'fee_id' => $fee->id,
                        'user_id' => $student->id,
                        'term_id' => $fee->term_id,
                    ],
                    [
                        'school_id' => $fee->school_id,
                        'original_amount' => $fee->amount,
                        'concession_amount' => $concessionAmount,
                        'amount_due' => $fee->amount - $concessionAmount,
                        'amount_paid' => $existing->first()?->amount_paid ?? 0,
                        'due_date' => $fee->due_date,
                        'status' => $existing->first()?->amount_paid >= ($fee->amount - $concessionAmount) ? 'paid' : 'pending',
                    ]
                );
            }
        });
    }

    private function calculateConcession(User $student, $fee)
    {
        // Check if student has active concession for this fee type
        return $student->feeConcessions()
            ->wherePivot('fee_type_id', $fee->fee_type_id)
            ->where(function ($q) use ($fee) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', $fee->due_date);
                $q->whereNull('end_date')->orWhere('end_date', '>=', $fee->due_date);
            })
            ->sum('amount');
    }
}

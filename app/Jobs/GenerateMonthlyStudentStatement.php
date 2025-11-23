<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

class GenerateMonthlyStudentStatement implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $students = User::whereHas('studentRecord')
            ->whereHas('feeAssignments', fn($q) => $q->where('balance', '>', 0))
            ->with(['studentRecord.classSection', 'feeAssignments.fee.feeType'])
            ->get();

        foreach ($students as $student) {
            // Generate PDF
            $pdfPath = $this->generateStatementPdf($student);

            // Send via email + save to storage
            Notification::send($student, new MonthlyStatementNotification($student, $pdfPath));
        }
    }

    private function generateStatementPdf(User $student)
    {
        $school = $student->school;

        // Generate Paystack/Flutterwave link
        $paymentLink = route('paystack.initialize', [
            'student_id' => $student->id,
            'amount' => $student->feeAssignments()->sum('balance') * 100, // kobo
            'reference' => 'STMT-' . $student->id . '-' . now()->format('Ym')
        ]);

        $qrCode = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')
            ->size(300)
            ->generate($paymentLink);

        $data = [
            'student' => $student,
            'school' => $school,
            'assignments' => $student->feeAssignments()->with('fee')->get(),
            'totalDue' => $student->feeAssignments()->sum('balance'),
            'qrCode' => 'data:image/png;base64,' . base64_encode($qrCode),
            'paymentLink' => $paymentLink,
            'month' => now()->format('F Y'),
        ];

        $pdf = \PDF::loadView('pdf.statement', $data)
            ->setPaper('a4')
            ->setOptions(['defaultFont' => 'DejaVu Sans']);

        $filename = "statements/{$student->id}-" . now()->format('Y-m') . ".pdf";
        Storage::put("public/{$filename}", $pdf->output());

        return Storage::url($filename);
    }
}

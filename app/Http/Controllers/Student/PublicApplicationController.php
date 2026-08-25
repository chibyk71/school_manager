<?php

namespace App\Http\Controllers\Student;

use App\Http\Requests\Student\SubmitApplicationRequest;
use App\Http\Resources\Student\StudentApplicationResource;
use App\Models\Student\AdmissionSession;
use App\Models\Student\StudentApplication;
use App\Services\Student\StudentApplicationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * PublicApplicationController – Public-Facing Student Admission Application Flow
 *
 * Handles the applicant-facing side of the admission pipeline — no authentication
 * required for viewing and starting an application. Submission requires the
 * applicant's verified contact details (email/phone) instead of a login.
 *
 * ── Flow ──────────────────────────────────────────────────────────────────────
 * 1. show()   → Applicant views the active admission session info + form
 * 2. store()  → Applicant submits form → creates draft/submitted application
 * 3. status() → Applicant checks their application status using reference number
 * 4. pay()    → Applicant pays application fee via gateway (redirects to gateway)
 *
 * ── Authorization ─────────────────────────────────────────────────────────────
 * This controller is intentionally public (no auth middleware). The rate limiting
 * on `store()` prevents abuse. Reference-number + email/phone is the identity
 * check for `status()`.
 *
 * ── No Policy Needed Here ─────────────────────────────────────────────────────
 * Public routes do not have an authenticated user. School scoping is enforced
 * via the `school_id` resolved from the subdomain/slug in middleware.
 *
 * ── Fits into the Student Management Module ──────────────────────────────────
 * - Routes (public, no auth):
 *     GET  /apply               → show active admission session + form
 *     POST /apply               → submit application
 *     GET  /apply/status        → check application status by reference number
 *     POST /apply/{id}/pay      → initiate payment
 * - Frontend: Public/Apply/Index.vue, Public/Apply/Status.vue
 * - Pairs with ApplicationController (admin side)
 */
class PublicApplicationController
{
    public function __construct(
        protected StudentApplicationService $applicationService
    ) {}

    /**
     * Display the active admission session and application form.
     * GET /apply
     *
     * Shows open admission sessions for the current school.
     * If no session is active, shows a "closed" message.
     */
    public function show(Request $request)
    {
        $school = GetSchoolModel();

        $session = AdmissionSession::where('school_id', $school?->id)
            ->where('is_active', true)
            ->where('application_opens_at', '<=', now())
            ->where('application_closes_at', '>=', now())
            ->first();

        return Inertia::render('Public/Apply/Index', [
            'session'     => $session,
            'schoolName'  => $school?->name,
            'isOpen'      => $session !== null,
        ]);
    }

    /**
     * Submit a new application.
     * POST /apply
     *
     * Rate-limited: 5 applications per IP per hour (configure in RouteServiceProvider).
     * On success: returns the reference number so the applicant can track status.
     */
    public function store(SubmitApplicationRequest $request)
    {
        try {
            $application = $this->applicationService->submitApplication(
                $request->validated()
            );

            return Inertia::render('Public/Apply/Submitted', [
                'referenceNumber' => $application->reference_number,
                'applicantName'   => trim("{$application->first_name} {$application->last_name}"),
                'applicationFee'  => $application->admissionSession?->application_fee,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();

        } catch (\Exception $e) {
            Log::error('Public application submission failed', [
                'school_id' => GetSchoolModel()?->id,
                'error'     => $e->getMessage(),
            ]);

            return back()
                ->withErrors(['error' => 'Unable to submit your application. Please try again.'])
                ->withInput();
        }
    }

    /**
     * Check application status by reference number.
     * GET /apply/status?ref=ADM-2025-0847&email=applicant@email.com
     *
     * No login required — identity is confirmed by reference + email/phone match.
     */
    public function status(Request $request)
    {
        $request->validate([
            'ref'   => ['required', 'string'],
            'email' => ['required_without:phone', 'nullable', 'email'],
            'phone' => ['required_without:email', 'nullable', 'string'],
        ]);

        $application = StudentApplication::where('reference_number', $request->string('ref'))
            ->where(function ($q) use ($request) {
                $q->where('email', $request->input('email'))
                  ->orWhere('phone', $request->input('phone'));
            })
            ->first();

        return Inertia::render('Public/Apply/Status', [
            'found'       => $application !== null,
            'application' => $application
                ? new StudentApplicationResource($application->load('examResult'))
                : null,
        ]);
    }

    /**
     * Initiate payment for the application fee.
     * POST /apply/{application}/pay
     *
     * Validates ownership via reference + email/phone before redirecting
     * to the payment gateway. The gateway webhook will update payment status.
     */
    public function pay(Request $request, StudentApplication $application)
    {
        $request->validate([
            'email' => ['required_without:phone', 'nullable', 'email'],
            'phone' => ['required_without:email', 'nullable', 'string'],
            'type'  => ['required', 'in:application_fee,acceptance_fee'],
        ]);

        // Verify the applicant owns this application
        $ownerMatch = $application->email === $request->input('email')
            || $application->phone === $request->input('phone');

        if (!$ownerMatch) {
            return back()->withErrors(['error' => 'Application not found or details do not match.']);
        }

        try {
            $paymentUrl = $this->applicationService->initiatePayment(
                application: $application,
                type:        $request->string('type'),
            );

            return redirect()->away($paymentUrl);

        } catch (\Exception $e) {
            Log::error('Failed to initiate application payment', [
                'application_id' => $application->id,
                'error'          => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Payment initiation failed. Please try again.']);
        }
    }
}

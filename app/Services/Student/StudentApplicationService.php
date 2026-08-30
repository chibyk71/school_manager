<?php

namespace App\Services\Student;

use App\Models\CustomField;
use App\Models\School;
use App\Models\Student\StudentApplication;
use App\Models\User;
use App\Notifications\Student\ApplicationApprovedNotification;
use App\Notifications\Student\ApplicationRejectedNotification;
use App\Notifications\Student\ApplicationSubmittedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * StudentApplicationService – Phase 2 Application lifecycle.
 *
 * Creates/submits/reviews applications. Does NOT create Profile, Student,
 * Admission, or Enrollment on approval.
 *
 * Custom Fields: uses the shared HasCustomFields / CustomFieldService engine
 * (model_type = StudentApplication::class). No parallel custom-field store.
 *
 * Application fee: records payment *state* on the application only.
 * Finance remains the source of truth for money movement (Payment / Fee /
 * gateway). Application exists ≠ fee paid.
 */
class StudentApplicationService
{
    public function submitPublicApplication(array $data, School $school): StudentApplication
    {
        return DB::transaction(function () use ($data, $school) {
            $customFields = $this->extractCustomFieldPayload($data);
            $application = new StudentApplication();
            $application->fill($this->sanitizeInput($data));
            $application->school_id = $school->id;
            $application->source = StudentApplication::SOURCE_PUBLIC;
            $application->status = StudentApplication::STATUS_SUBMITTED;
            $application->submitted_at = now();
            $application->assignApplicationNumber($school);
            $application->assignToken();
            $this->initializeFeePaymentState($application, $school);
            $application->save();

            $this->persistCustomFieldResponses($application, $customFields, $school);
            $this->notifySubmitted($application);

            Log::info('Public application submitted', [
                'application_id' => $application->id,
                'school_id' => $school->id,
                'application_number' => $application->application_number,
                'fee_payment_status' => $application->fee_payment_status,
            ]);

            return $application->fresh(['customFieldResponses.customField']);
        });
    }

    public function submitStaffApplication(array $data, School $school, User $staff): StudentApplication
    {
        return DB::transaction(function () use ($data, $school, $staff) {
            $customFields = $this->extractCustomFieldPayload($data);
            $application = new StudentApplication();
            $application->fill($this->sanitizeInput($data));
            $application->school_id = $school->id;
            $application->source = StudentApplication::SOURCE_STAFF;
            $application->status = StudentApplication::STATUS_SUBMITTED;
            $application->submitted_at = now();
            $application->assignApplicationNumber($school);
            $application->assignToken();
            $this->initializeFeePaymentState($application, $school);
            $application->save();

            $this->persistCustomFieldResponses($application, $customFields, $school);

            Log::info('Staff application created', [
                'application_id' => $application->id,
                'school_id' => $school->id,
                'staff_id' => $staff->id,
            ]);

            return $application->fresh(['customFieldResponses.customField']);
        });
    }

    public function beginReview(StudentApplication $application, User $reviewer): StudentApplication
    {
        return DB::transaction(function () use ($application, $reviewer) {
            $locked = StudentApplication::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();

            $locked->transitionTo(StudentApplication::STATUS_UNDER_REVIEW);
            // reviewed_by set when review starts so concurrent staff see ownership;
            // reviewed_at remains null until a terminal decision (approve/reject).
            $locked->reviewed_by = $reviewer->id;
            $locked->save();

            return $locked->fresh();
        });
    }

    public function approveApplication(StudentApplication $application, User $reviewer, ?string $notes = null): StudentApplication
    {
        return DB::transaction(function () use ($application, $reviewer, $notes) {
            $locked = StudentApplication::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();

            $locked->transitionTo(StudentApplication::STATUS_APPROVED);
            $locked->reviewed_by = $reviewer->id;
            $locked->reviewed_at = now();
            if ($notes !== null) {
                $locked->admin_notes = $notes;
            }
            $locked->save();

            $this->notifyApproved($locked);

            Log::info('Application approved (not admitted)', [
                'application_id' => $locked->id,
                'reviewer_id' => $reviewer->id,
            ]);

            return $locked->fresh();
        });
    }

    public function rejectApplication(StudentApplication $application, string $reason, User $reviewer): StudentApplication
    {
        if (strlen(trim($reason)) < 5) {
            throw ValidationException::withMessages([
                'rejection_reason' => 'A meaningful rejection reason is required.',
            ]);
        }

        return DB::transaction(function () use ($application, $reason, $reviewer) {
            $locked = StudentApplication::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();

            $locked->transitionTo(StudentApplication::STATUS_REJECTED);
            $locked->rejection_reason = $reason;
            $locked->reviewed_by = $reviewer->id;
            $locked->reviewed_at = now();
            $locked->save();

            $this->notifyRejected($locked);

            Log::info('Application rejected', [
                'application_id' => $locked->id,
                'reviewer_id' => $reviewer->id,
            ]);

            return $locked->fresh();
        });
    }

    public function withdrawApplication(StudentApplication $application): StudentApplication
    {
        return DB::transaction(function () use ($application) {
            $locked = StudentApplication::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            $locked->transitionTo(StudentApplication::STATUS_WITHDRAWN);
            $locked->save();

            return $locked->fresh();
        });
    }

    public function applicationsRequired(?School $school = null): bool
    {
        $school = $school ?? (function_exists('GetSchoolModel') ? GetSchoolModel() : null);
        if (! $school) {
            return false;
        }

        $settings = function_exists('getMergedSettings')
            ? (getMergedSettings('academic.application', $school) ?? [])
            : [];

        return (bool) ($settings['required'] ?? false);
    }

    /**
     * Configuration only – does not imply payment occurred.
     *
     * @return array{required: bool, amount: float|null, fee_type: string|null}
     */
    public function applicationFeeConfig(?School $school = null): array
    {
        $school = $school ?? (function_exists('GetSchoolModel') ? GetSchoolModel() : null);
        $settings = function_exists('getMergedSettings') && $school
            ? (getMergedSettings('academic.application', $school) ?? [])
            : [];

        return [
            'required' => (bool) ($settings['fee_required'] ?? false),
            'amount' => isset($settings['fee_amount']) ? (float) $settings['fee_amount'] : null,
            'fee_type' => $settings['fee_type'] ?? 'application_fee',
        ];
    }

    /**
     * Effective custom fields for the application form (school-configured).
     *
     * @return Collection<\App\Models\CustomField>
     */
    public function effectiveApplicationFields(?School $school = null): Collection
    {
        $school = $school ?? (function_exists('GetSchoolModel') ? GetSchoolModel() : null);
        if (! $school) {
            return collect();
        }

        return CustomField::effectiveFor($school, StudentApplication::class);
    }

    /**
     * Record that Finance confirmed payment for this application.
     *
     * Call this from a Finance listener / payment callback once Payment is
     * completed. Does not create Payment rows – Finance owns that.
     */
    public function recordApplicationFeePaid(
        StudentApplication $application,
        string $paymentReference,
        ?\DateTimeInterface $paidAt = null
    ): StudentApplication {
        return DB::transaction(function () use ($application, $paymentReference, $paidAt) {
            $locked = StudentApplication::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            $locked->fee_payment_status = StudentApplication::FEE_PAID;
            $locked->fee_payment_reference = $paymentReference;
            $locked->fee_paid_at = $paidAt ? \Illuminate\Support\Carbon::instance($paidAt) : now();
            $locked->save();

            return $locked->fresh();
        });
    }

    public function waiveApplicationFee(StudentApplication $application, User $actor, ?string $reason = null): StudentApplication
    {
        return DB::transaction(function () use ($application, $actor, $reason) {
            $locked = StudentApplication::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            $locked->fee_payment_status = StudentApplication::FEE_WAIVED;
            if ($reason) {
                $locked->admin_notes = trim(($locked->admin_notes ? $locked->admin_notes."\n" : '').'Fee waived: '.$reason);
            }
            $locked->save();

            Log::info('Application fee waived', [
                'application_id' => $locked->id,
                'actor_id' => $actor->id,
            ]);

            return $locked->fresh();
        });
    }

    /**
     * Snapshot payment state for UI / policy checks.
     *
     * @return array{status: string, satisfied: bool, reference: string|null, paid_at: string|null, config: array}
     */
    public function applicationFeeState(StudentApplication $application, ?School $school = null): array
    {
        $school = $school ?? $application->school;
        $config = $this->applicationFeeConfig($school);

        return [
            'status' => $application->fee_payment_status ?? StudentApplication::FEE_NOT_REQUIRED,
            'satisfied' => $application->isApplicationFeeSatisfied(),
            'reference' => $application->fee_payment_reference,
            'paid_at' => $application->fee_paid_at?->toIso8601String(),
            'config' => $config,
        ];
    }

    protected function initializeFeePaymentState(StudentApplication $application, School $school): void
    {
        $config = $this->applicationFeeConfig($school);

        if ($config['required']) {
            $application->fee_payment_status = StudentApplication::FEE_UNPAID;
        } else {
            $application->fee_payment_status = StudentApplication::FEE_NOT_REQUIRED;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractCustomFieldPayload(array &$data): array
    {
        $payload = [];
        if (isset($data['custom_fields']) && is_array($data['custom_fields'])) {
            $payload = $data['custom_fields'];
        } elseif (isset($data['custom_data']) && is_array($data['custom_data'])) {
            // Legacy key still accepted; preferred key is custom_fields.
            $payload = $data['custom_data'];
        }
        unset($data['custom_fields'], $data['custom_data']);

        return $payload;
    }

    /**
     * Persist via the shared Custom Fields engine (required fields enforced).
     *
     * @param  array<string, mixed>  $responses
     */
    protected function persistCustomFieldResponses(
        StudentApplication $application,
        array $responses,
        School $school
    ): void {
        // Ensure school context for HasCustomFields helpers.
        if (function_exists('app') && app()->bound('schoolManager')) {
            try {
                app('schoolManager')->setActiveSchool($school);
            } catch (\Throwable) {
                // Non-fatal in isolated unit tests without schoolManager.
            }
        }

        $fields = CustomField::effectiveFor($school, StudentApplication::class)->keyBy('name');

        // Enforce required configured fields even when omitted from payload.
        $missingRequired = [];
        foreach ($fields as $name => $field) {
            if ($field->required && ! array_key_exists($name, $responses)) {
                $missingRequired[$name] = ($field->label ?? $name).' is required.';
            }
        }
        if ($missingRequired) {
            throw ValidationException::withMessages($missingRequired);
        }

        if ($responses === []) {
            return;
        }

        // Restrict to known fields only.
        $filtered = array_intersect_key($responses, $fields->all());
        if ($filtered === []) {
            return;
        }

        $application->saveCustomFieldResponses($filtered, true);

        // Mirror a lightweight snapshot into custom_data for list/search convenience
        // without replacing the authoritative custom_field_responses store.
        $snapshot = [];
        foreach ($filtered as $name => $value) {
            if (! is_object($value)) {
                $snapshot[$name] = $value;
            }
        }
        if ($snapshot !== []) {
            $application->custom_data = array_merge($application->custom_data ?? [], $snapshot);
            $application->saveQuietly();
        }
    }

    protected function sanitizeInput(array $data): array
    {
        unset(
            $data['school_id'],
            $data['status'],
            $data['reviewed_by'],
            $data['reviewed_at'],
            $data['application_number'],
            $data['application_token'],
            $data['student_id'],
            $data['source'],
            $data['fee_payment_status'],
            $data['fee_payment_reference'],
            $data['fee_paid_at'],
            $data['custom_fields'],
            $data['custom_data'],
        );

        return $data;
    }

    protected function notifySubmitted(StudentApplication $application): void
    {
        try {
            $email = $application->email
                ?? data_get($application->guardians_data, '0.email');

            if ($email) {
                Notification::route('mail', $email)
                    ->notify(new ApplicationSubmittedNotification($application));
            }
        } catch (\Throwable $e) {
            Log::warning('Application submitted notification failed', [
                'application_id' => $application->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function notifyApproved(StudentApplication $application): void
    {
        try {
            $email = $application->email
                ?? data_get($application->guardians_data, '0.email');

            if ($email) {
                Notification::route('mail', $email)
                    ->notify(new ApplicationApprovedNotification($application));
            }
        } catch (\Throwable $e) {
            Log::warning('Application approved notification failed', [
                'application_id' => $application->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function notifyRejected(StudentApplication $application): void
    {
        try {
            $email = $application->email
                ?? data_get($application->guardians_data, '0.email');

            if ($email) {
                Notification::route('mail', $email)
                    ->notify(new ApplicationRejectedNotification($application));
            }
        } catch (\Throwable $e) {
            Log::warning('Application rejected notification failed', [
                'application_id' => $application->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

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
 * Custom Fields: uses the shared HasCustomFields / CustomFieldService engine.
 * Fee: stores outcome state only; Finance owns Payment/Fee records.
 */
class StudentApplicationService
{
    public function submitPublicApplication(array $data, School $school): StudentApplication
    {
        return DB::transaction(function () use ($data, $school) {
            $customFields = $data['custom_fields'] ?? $data['custom_data'] ?? [];

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
            ]);

            return $application->fresh(['customFieldResponses.customField']);
        });
    }

    public function submitStaffApplication(array $data, School $school, User $staff): StudentApplication
    {
        return DB::transaction(function () use ($data, $school, $staff) {
            $customFields = $data['custom_fields'] ?? $data['custom_data'] ?? [];

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
     * Does not create Payment rows – Finance owns that.
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

    public function waiveApplicationFee(StudentApplication $application): StudentApplication
    {
        return DB::transaction(function () use ($application) {
            $locked = StudentApplication::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            $locked->fee_payment_status = StudentApplication::FEE_WAIVED;
            $locked->fee_paid_at = now();
            $locked->save();

            return $locked->fresh();
        });
    }

    protected function initializeFeePaymentState(StudentApplication $application, School $school): void
    {
        $config = $this->applicationFeeConfig($school);
        $application->fee_payment_status = $config['required']
            ? StudentApplication::FEE_UNPAID
            : StudentApplication::FEE_NOT_REQUIRED;
    }

    protected function persistCustomFieldResponses(StudentApplication $application, array $responses, School $school): void
    {
        if (empty($responses)) {
            return;
        }

        $fields = $this->effectiveApplicationFields($school)->keyBy('name');

        foreach ($fields as $name => $field) {
            if ($field->required && ! array_key_exists($name, $responses)) {
                throw ValidationException::withMessages([
                    "custom_fields.{$name}" => "The {$field->label} field is required.",
                ]);
            }
        }

        // Ensure school context for HasCustomFields helpers.
        if (function_exists('GetSchoolModel') && GetSchoolModel() === null && app()->bound('schoolManager')) {
            try {
                app('schoolManager')->setActiveSchool($school);
            } catch (\Throwable $e) {
                // best-effort
            }
        }

        $application->saveCustomFieldResponses($responses);
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
            // Section placement is out of scope for Phase 2 Application workflow.
            $data['school_section_id'],
        );

        return $data;
    }

    protected function notifySubmitted(StudentApplication $application): void
    {
        $this->lifecycleNotify($application, 'application_submitted', ApplicationSubmittedNotification::class);
    }

    protected function notifyApproved(StudentApplication $application): void
    {
        $this->lifecycleNotify($application, 'application_approved', ApplicationApprovedNotification::class);
    }

    protected function notifyRejected(StudentApplication $application): void
    {
        $this->lifecycleNotify($application, 'application_rejected', ApplicationRejectedNotification::class);
    }

    protected function lifecycleNotify(StudentApplication $application, string $preferenceKey, string $notificationClass): void
    {
        try {
            $school = School::query()->find($application->school_id);
            if (! $school) {
                return;
            }
            app(LifecycleNotificationService::class)->notify(
                $school,
                $preferenceKey,
                $notificationClass,
                $application
            );
        } catch (\Throwable $e) {
            Log::warning('Application lifecycle notification failed', [
                'application_id' => $application->id,
                'preference' => $preferenceKey,
                'error' => $e->getMessage(),
            ]);
        }
    }
}


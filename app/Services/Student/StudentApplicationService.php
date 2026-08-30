<?php

namespace App\Services\Student;

use App\Models\School;
use App\Models\Student\StudentApplication;
use App\Models\User;
use App\Notifications\Student\ApplicationApprovedNotification;
use App\Notifications\Student\ApplicationRejectedNotification;
use App\Notifications\Student\ApplicationSubmittedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * StudentApplicationService – Phase 2 Application lifecycle.
 *
 * Creates/submits/reviews applications. Does NOT create Profile, Student,
 * Admission, or Enrollment on approval.
 */
class StudentApplicationService
{
    public function submitPublicApplication(array $data, School $school): StudentApplication
    {
        return DB::transaction(function () use ($data, $school) {
            $application = new StudentApplication();
            $application->fill($this->sanitizeInput($data));
            $application->school_id = $school->id;
            $application->source = StudentApplication::SOURCE_PUBLIC;
            $application->status = StudentApplication::STATUS_SUBMITTED;
            $application->submitted_at = now();
            $application->assignApplicationNumber($school);
            $application->assignToken();
            $application->save();

            $this->notifySubmitted($application);

            Log::info('Public application submitted', [
                'application_id' => $application->id,
                'school_id' => $school->id,
                'application_number' => $application->application_number,
            ]);

            return $application->fresh();
        });
    }

    public function submitStaffApplication(array $data, School $school, User $staff): StudentApplication
    {
        return DB::transaction(function () use ($data, $school, $staff) {
            $application = new StudentApplication();
            $application->fill($this->sanitizeInput($data));
            $application->school_id = $school->id;
            $application->source = StudentApplication::SOURCE_STAFF;
            $application->status = StudentApplication::STATUS_SUBMITTED;
            $application->submitted_at = now();
            $application->assignApplicationNumber($school);
            $application->assignToken();
            $application->save();

            Log::info('Staff application created', [
                'application_id' => $application->id,
                'school_id' => $school->id,
                'staff_id' => $staff->id,
            ]);

            return $application->fresh();
        });
    }

    public function beginReview(StudentApplication $application, User $reviewer): StudentApplication
    {
        return DB::transaction(function () use ($application, $reviewer) {
            $application->transitionTo(StudentApplication::STATUS_UNDER_REVIEW);
            $application->reviewed_by = $reviewer->id;
            $application->save();

            return $application->fresh();
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

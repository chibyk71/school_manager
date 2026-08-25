<?php

namespace App\Models\Student;

use App\Models\School;
use App\Models\SchoolSection;
use App\Models\User;
use App\Traits\BelongsToSchool;
use App\Traits\HasDynamicEnum;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * StudentApplication Model – Application Entry Point (v2.0 – Production-Ready)
 *
 * This model represents a student application, whether submitted through the public portal
 * or created directly by an admin. It acts as a temporary holding area for unverified data
 * before it is reviewed and converted into a Profile + Student record upon admission.
 *
 * Features / Problems Solved:
 * - Separates raw application data from canonical student records (clean audit trail).
 * - Supports both public_portal and admin_direct sources with different workflows.
 * - Stores personal snapshot + raw guardians_data + custom_data (JSON) before Profile/Student exists.
 * - Uses HasDynamicEnum for status and gender (school-customizable where needed).
 * - Full multi-tenant safety via BelongsToSchool trait.
 * - Ready for DataTables via HasTableQuery (with advanced filtering by status, session, etc.).
 * - On admission: data is mapped to Profile → Student → Guardian links → CustomFieldResponses.
 * - Soft deletes allowed for archiving old/rejected applications without losing history.
 *
 * Lifecycle:
 *   pending → admitted (student_id populated)
 *   pending → rejected (rejection_reason recorded)
 *   pending → withdrawn
 *
 * Fits into the Student Management Module:
 * - Central model for PublicApplicationController and ApplicationController.
 * - Heavily used by StudentApplicationService (submitPublicApplication, admitApplication, rejectApplication).
 * - Feeds data into Enrollment Wizard and Student creation flow.
 * - Frontend: Applications/Index.vue (DataTable), Applications/Show.vue, and public tracking page.
 *
 * Important Notes:
 * - Personal fields (first_name, date_of_birth, etc.) are snapshots only.
 *   On admission they are used to create/update the central Profile record.
 * - guardians_data (JSON) is parsed and converted into proper Guardian + pivot records during admission.
 * - custom_data (JSON) is mapped to HasCustomFields on the resulting Student.
 * - No direct BelongsTo Profile here — Profile is created only on successful admission.
 */

class StudentApplication extends Model
{
    use HasFactory,
        HasUuids,
        SoftDeletes,
        BelongsToSchool,
        HasDynamicEnum,
        HasTableQuery;

    protected $fillable = [
        'school_id',
        'academic_session_id',
        'school_section_id',
        'class_level_id',
        'first_name',
        'last_name',
        'middle_name',
        'date_of_birth',
        'gender',
        'phone',
        'email',
        'nationality',
        'state_of_origin',
        'religion',
        'blood_group',
        'previous_school',
        'previous_class',
        'previous_school_address',
        'guardians_data',
        'source',
        'status',
        'application_number',
        'application_token',
        'reviewed_by',
        'submitted_at',
        'reviewed_at',
        'rejection_reason',
        'admin_notes',
        'student_id',
        'documents',
        'custom_data',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'guardians_data' => 'array',
        'documents' => 'array',
        'custom_data' => 'array',
        'status' => 'string',
        'source' => 'string',
        'gender' => 'string',      // DynamicEnum support
        'religion' => 'string',      // DynamicEnum support
        'blood_group' => 'string',      // DynamicEnum support
    ];

    // For HasTableQuery trait – global search fields
    protected array $globalFilterFields = [
        'application_number',
        'first_name',
        'last_name',
        'phone',
        'email',
        'status',
        'source',
    ];

    /**
     * Dynamic enum properties – school-customizable where appropriate
     *
     * 'status'   → pending, admitted, rejected, withdrawn (can be extended per school)
     * 'gender'   → male, female, other, prefer_not_to_say
     * 'religion' → Christian, Muslim, Traditional, etc.
     */
    public function getDynamicEnumProperties(): array
    {
        return ['status', 'gender', 'religion'];
    }

    // =================================================================
    // RELATIONSHIPS
    // =================================================================

    /**
     * The school this application belongs to
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Desired academic session for enrollment
     */
    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Academic\AcademicSession::class);
    }

    /**
     * Desired school section (e.g. Nursery, Primary, Secondary)
     */
    public function schoolSection(): BelongsTo
    {
        return $this->belongsTo(SchoolSection::class);
    }

    /**
     * Desired class level (e.g. Primary 3, JSS 1, SSS 2)
     */
    public function classLevel(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Academic\ClassLevel::class);
    }

    /**
     * Admin who reviewed this application
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * The resulting Student record (populated after successful admission)
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    // =================================================================
    // SCOPES
    // =================================================================

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeAdmitted($query)
    {
        return $query->where('status', 'admitted');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeFromPublicPortal($query)
    {
        return $query->where('source', 'public_portal');
    }

    public function scopeForSession($query, $sessionId)
    {
        return $query->where('academic_session_id', $sessionId);
    }

    // =================================================================
    // ACCESSORS
    // =================================================================

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
    }

    public function getIsExpiredAttribute(): bool
    {
        // Optional: Add token expiration logic if you implement time-limited tokens
        return false;
    }

    // =================================================================
    // HELPERS
    // =================================================================

    /**
     * Generate a unique application number (called in service layer)
     */
    public function generateApplicationNumber(): string
    {
        $year = now()->year;
        $prefix = 'APP';
        $sequence = str_pad($this->id ?? rand(1000, 9999), 6, '0', STR_PAD_LEFT);
        return "{$prefix}-{$year}-{$sequence}";
    }

    /**
     * Generate secure tracking token (called in service layer)
     */
    public function generateToken(): string
    {
        return \Str::random(64);
    }
}

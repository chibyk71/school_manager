<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use App\Traits\BelongsToSections;
use App\Traits\HasCustomFields;
use App\Traits\HasDynamicEnum;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Staff Model – Employment / Position Record (v1.0 – Production-Ready)
 *
 * Represents a single employment position or role for a person (linked via Profile).
 * Supports multiple positions per person (e.g., teacher in School A, coordinator in School B,
 * or sequential roles over time in the same school).
 *
 * Features / Problems Solved:
 * - Time-bound & multi-school capable: New record for new hires, transfers, promotions,
 *   or role changes — preserves historical employment data (important for service records,
 *   pension calculations, reference letters).
 * - School & optional section scoping: Uses BelongsToSchool trait → automatic filtering
 *   to current school context (multi-tenant safety out of the box).
 * - Central personal data: Name, DOB, gender, phone, photo, addresses, email all live on
 *   Profile → zero duplication across positions.
 * - Department linkage: belongsToMany Department (or DepartmentRole) for flexible HRM
 *   integration (subjects taught, admin roles, etc.).
 * - Extensibility: HasCustomFields for school-defined attributes (qualifications, certifications,
 *   salary scale, contract end date, emergency contact, bank details, etc.).
 * - Dynamic enums: HasDynamicEnum ready for employment_type (full-time/part-time/contract),
 *   status (active/on-leave/terminated/resigned).
 * - Soft deletes: Archive old positions without losing history.
 * - Performance: Indexes on foreign keys + staff_id_number; prepared for HasTableQuery
 *   trait (advanced filtering, sorting, global search in data tables).
 * - Clean, explicit relationships: No polymorphism — direct belongsTo Profile.
 *
 * Fits into the User Management Module:
 * - Created via StaffController (hire/assign position actions) — workflow usually:
 *     1. Find or create Profile
 *     2. Create new Staff record linked to that profile
 *     3. Optionally create User + assign permissions/roles if login needed
 *     4. Assign departments/sections
 * - Never manipulated directly via UI — all CRUD goes through staff-specific
 *   controllers/modals (StaffAssignmentModal.vue, position history view).
 * - Profile is read-mostly from here: Updates to name/photo/phone should happen via
 *   profile editing in staff context or rare admin merge tool.
 * - Integrates with:
 *   - Frontend: StaffTable.vue (powered by HasTableQuery), StaffAssignmentModal.vue
 *   - Backend: StaffController, potential future HRMService (salary, leave, appraisal)
 *   - Other modules: Attendance (via morphMany), Payroll, Leave Management
 *
 * Important Conventions:
 * - No direct profile creation/editing from Staff model/controller.
 * - Profile manipulation restricted to staff-specific flows or admin tools.
 * - This model is NOT the source of truth for personal data — Profile is.
 * - staff_id_number should be unique per school (add composite unique index if needed).
 */

class Staff extends Model
{
    use HasFactory,
        HasUuids,
        SoftDeletes,
        BelongsToSchool,
        BelongsToSections,
        HasCustomFields,
        HasDynamicEnum,
        HasTableQuery;

    protected $fillable = [
        'profile_id',
        'school_id',
        'staff_id_number',
        'date_of_employment',
        'date_of_termination',
        'employment_type',
        'status',
        'notes',
    ];

    protected $casts = [
        'date_of_employment'  => 'date',
        'date_of_termination' => 'date',
        'employment_type'     => 'string', // ready for HasDynamicEnum
        'status'              => 'string', // ready for HasDynamicEnum
    ];

    // Fields for global search / HasTableQuery trait
    protected array $globalFilterFields = [
        'staff_id_number',
        'profile.first_name',
        'profile.last_name',
        'profile.phone',
        'status',
        'employment_type',
    ];

    // Dynamic enum properties (HasDynamicEnum trait)
    public function getDynamicEnumProperties(): array
    {
        return ['employment_type', 'status'];
    }

    // =================================================================
    // RELATIONSHIPS
    // =================================================================

    /**
     * The person this position belongs to (central identity)
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    // =================================================================
    // ACCESSORS (table/display helpers)
    // =================================================================

    public function getFullNameAttribute()
    {
        return $this->profile?->full_name ?? 'Unknown Staff';
    }

    public function getPhotoUrlAttribute()
    {
        return $this->profile?->photo_url ?? asset('images/avatars/default-male.png');
    }

    public function getYearsOfServiceAttribute(): ?int
    {
        if (!$this->date_of_employment) {
            return null;
        }

        return now()->diffInYears($this->date_of_employment);
    }

    public function getIsActiveAttribute(): bool
    {
        return is_null($this->date_of_termination);
    }

    public function getEmploymentStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'active'      => 'Active',
            'on_leave'    => 'On Leave',
            'suspended'   => 'Suspended',
            'resigned'    => 'Resigned',
            'terminated'  => 'Terminated',
            default       => ucfirst($this->status ?? 'Unknown'),
        };
    }

    // =================================================================
    // SCOPES
    // =================================================================

    public function scopeActive($query)
    {
        return $query->whereNull('date_of_termination')
                     ->where('status', 'active');
    }

    public function scopeInSection($query, $sectionId)
    {
        return $query->where('section_id', $sectionId);
    }

    public function scopeHiredAfter($query, $date)
    {
        return $query->where('date_of_employment', '>=', $date);
    }

    // =================================================================
    // HELPERS
    // =================================================================

    /**
     * Check if this position is current
     */
    public function isCurrent(): bool
    {
        return $this->isActive && in_array($this->status, ['active', 'on_leave']);
    }
}

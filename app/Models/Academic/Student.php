<?php

namespace App\Models\Academic;

use App\Models\Guardian;
use App\Models\Profile;
use App\Models\SchoolSection;
use App\Traits\BelongsToSchool;
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
 * Student Model – Enrollment Record (v1.0 – Production-Ready)
 *
 * Represents a single enrollment / student role for a person (linked via Profile).
 * Each time a student changes section, school, or re-enrolls (even in the same school),
 * a **new Student record** is created — this preserves historical data while allowing
 * different enrollment-specific information (e.g. new admission number, updated health info).
 *
 * Features / Problems Solved:
 * - Time-bound student identity: Supports graduation → re-enrollment workflows without
 *   mutating old records (historical integrity for transcripts, alumni tracking, audits).
 * - School & section scoping: Uses BelongsToSchool trait → all queries automatically
 *   filtered to current school context (multi-tenant safety).
 * - Central personal data: All name, DOB, gender, photo, phone, addresses come from Profile
 *   → no duplication across enrollments.
 * - Guardian relationships: belongsToMany Guardian via pivot (student_guardian) with
 *   relationship_type, is_primary, notes — flexible many-to-many.
 * - Extensibility: HasCustomFields for school-defined attributes (allergies, medical
 *   history, uniform size, transport route, etc.).
 * - Dynamic enums: HasDynamicEnum for status (active/graduated/suspended/left).
 * - Soft deletes: Archive old enrollments without permanent loss.
 * - Performance: Indexes on foreign keys + admission_number; prepared for HasTableQuery
 *   trait (advanced filtering/sorting/search in data tables).
 * - Clean relationships: No polymorphic mess — direct belongsTo Profile.
 *
 * Fits into the User Management Module:
 * - Created via StudentController (enroll/re-enroll actions) — usually bundles:
 *     1. Find or create Profile
 *     2. Create new Student record linked to that profile
 *     3. Optionally assign guardians via pivot
 * - Never manipulated directly via UI — all CRUD flows go through student-specific
 *   controllers/modals (StudentEnrollmentModal.vue, re-enrollment wizard).
 * - Profile is read-only from here: Updates to name/photo/phone should happen via
 *   profile editing in staff/guardian contexts or a rare admin merge tool.
 * - Integrates with:
 *   - Frontend: StudentsTable.vue (uses HasTableQuery), StudentEnrollmentModal.vue
 *   - Backend: StudentController, EnrollmentService (handles re-enrollment logic)
 *   - Other modules: Attendance, Fees, Academic (class assignments via future pivots)
 *
 * Important Conventions:
 * - No direct profile creation/editing from Student model/controller.
 * - Profile manipulation is restricted to staff/guardian flows or admin tools.
 * - This model is **not** the source of truth for personal data — Profile is.
 */

class Student extends Model
{
    use HasFactory,
        HasUuids,
        SoftDeletes,
        BelongsToSchool,
        HasCustomFields,
        HasDynamicEnum,
        HasTableQuery;

    protected $fillable = [
        'profile_id',
        'school_id',
        'section_id',
        'admission_number',
        'enrollment_date',
        'graduation_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'enrollment_date' => 'date',
        'graduation_date'  => 'date',
        'status'           => 'string', // ready for HasDynamicEnum
    ];

    // Fields for global search (HasTableQuery)
    protected array $globalFilterFields = [
        'admission_number',
        'profile.first_name',
        'profile.last_name',
        'profile.phone',
        'status',
    ];

    // Dynamic enum properties (HasDynamicEnum trait)
    public function getDynamicEnumProperties(): array
    {
        return ['status'];
    }

    // =================================================================
    // RELATIONSHIPS
    // =================================================================

    /**
     * The person this enrollment belongs to (central identity)
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * Current section (nursery, primary, secondary, etc.)
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(SchoolSection::class);
    }

    /**
     * All guardians responsible for this student
     */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'student_guardian')
                    ->withPivot('relationship_type', 'is_primary', 'notes')
                    ->withTimestamps();
    }

    /**
     * Primary guardian (convenience query)
     */
    public function primaryGuardian()
    {
        return $this->guardians()->wherePivot('is_primary', true)->first();
    }

    // =================================================================
    // ACCESSORS (for tables & display)
    // =================================================================

    public function getFullNameAttribute()
    {
        return $this->profile?->full_name ?? 'Unknown Student';
    }

    public function getPhotoUrlAttribute()
    {
        return $this->profile?->photo_url ?? asset('images/avatars/default-male.png');
    }

    public function getAgeAttribute()
    {
        return $this->profile?->age;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'active'     => 'Active',
            'graduated'  => 'Graduated',
            'suspended'  => 'Suspended',
            'transferred'=> 'Transferred',
            'left'       => 'Left',
            default      => ucfirst($this->status ?? 'Unknown'),
        };
    }

    // =================================================================
    // SCOPES
    // =================================================================

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInSection($query, $sectionId)
    {
        return $query->where('section_id', $sectionId);
    }

    public function scopeEnrolledAfter($query, $date)
    {
        return $query->where('enrollment_date', '>=', $date);
    }

    // =================================================================
    // HELPERS
    // =================================================================

    /**
     * Check if this enrollment is current (not graduated/left)
     */
    public function isCurrent(): bool
    {
        return in_array($this->status, ['active', 'suspended']);
    }

    /**
     * Get primary guardian's phone (fallback chain)
     */
    public function getPrimaryGuardianPhone(): ?string
    {
        return $this->primaryGuardian()?->profile?->phone
            ?? $this->guardians()->first()?->profile?->phone;
    }
}

<?php

namespace App\Models;

use App\Models\Academic\Student;
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
 * Guardian Model – Responsible Person / Ward Guardian Record (v1.0 – Production-Ready)
 *
 * Represents a guardian role for a person (linked via central Profile).
 * Allows independent creation of guardians (before linking to any student)
 * and supports tenant-wide or school-specific guardians (school_id nullable).
 *
 * Features / Problems Solved:
 * - Independent guardian records: Can create guardians standalone (e.g., pre-register parents)
 *   and link them to students later — fits real admission workflows.
 * - Central personal data: Name, DOB, gender, phone, photo, addresses, email all live on
 *   Profile → no duplication across guardian-student links.
 * - Flexible relationships: belongsToMany Student via pivot (student_guardian) with
 *   relationship_type (father/mother/sponsor), is_primary (priority contact), notes.
 * - School scoping: BelongsToSchool trait (nullable school_id) → can be tenant-wide
 *   or tied to a specific school; queries respect current school context when school_id set.
 * - Extensibility: HasCustomFields for school-defined attributes (occupation, employer,
 *   income bracket, preferred contact method, legal documents, emergency priority, etc.).
 * - Dynamic enums: HasDynamicEnum ready for future fields (e.g., guardian_type: parent/sponsor/relative).
 * - Soft deletes: Archive inactive guardians without losing historical links.
 * - Performance: Indexes on foreign keys; prepared for HasTableQuery trait (advanced
 *   filtering, sorting, global search in guardian listings).
 * - Clean, explicit relationships: No polymorphism — direct belongsTo Profile.
 *
 * Fits into the User Management Module:
 * - Created via GuardianController (standalone registration) or inline during
 *   student enrollment (StudentEnrollmentModal.vue).
 * - Linked to students via pivot operations (AssignGuardianModal.vue).
 * - Never manipulates Profile directly — profile edits happen in guardian context
 *   or rare admin merge tool.
 * - Integrates with:
 *   - Frontend: GuardiansTable.vue (HasTableQuery-powered), GuardianFormModal.vue,
 *     AssignGuardianModal.vue (multi-select or inline create)
 *   - Backend: GuardianController for CRUD; pivot logic in StudentController or service
 *   - Other modules: Notifications (primary guardian priority), Emergency protocols
 *
 * Important Conventions:
 * - Profile is the source of truth for personal data and avatar — Guardian model
 *   only owns guardian-specific metadata and relationships.
 * - No direct profile creation/editing from Guardian model/controller.
 * - school_id is nullable → supports cross-school guardians (common in family groups).
 * - Heavy reliance on custom fields → makes the model very adaptable without schema changes.
 */

class Guardian extends Model
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
        'notes',
    ];

    // For global search / HasTableQuery trait
    protected array $globalFilterFields = [
        'profile.first_name',
        'profile.last_name',
        'profile.phone',
        'notes',
    ];

    // Optional dynamic enum properties (if you add enum-like fields later)
    public function getDynamicEnumProperties(): array
    {
        return []; // e.g. ['guardian_type'] if added
    }

    // =================================================================
    // RELATIONSHIPS
    // =================================================================

    /**
     * The central person / identity this guardian role belongs to
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * Optional school scoping (nullable — can be tenant-wide)
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * All students/wards this guardian is responsible for
     */
    public function wards(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_guardian')
                    ->withPivot('relationship_type', 'is_primary', 'notes')
                    ->withTimestamps();
    }

    /**
     * Primary ward (convenience — e.g. for display or default contact)
     */
    public function primaryWard()
    {
        return $this->wards()->wherePivot('is_primary', true)->first();
    }

    // =================================================================
    // ACCESSORS (table/display helpers)
    // =================================================================

    public function getFullNameAttribute()
    {
        return $this->profile?->full_name ?? 'Unknown Guardian';
    }

    public function getPhotoUrlAttribute()
    {
        return $this->profile?->photo_url ?? asset('images/avatars/default-male.png');
    }

    public function getPhoneAttribute()
    {
        return $this->profile?->phone;
    }

    public function getEmailAttribute()
    {
        return $this->profile?->email ?? $this->profile?->user?->email;
    }

    public function getHasWardsAttribute(): bool
    {
        return $this->wards()->exists();
    }

    // =================================================================
    // SCOPES
    // =================================================================

    public function scopeWithWards($query)
    {
        return $query->whereHas('wards');
    }

    public function scopeWithoutWards($query)
    {
        return $query->doesntHave('wards');
    }

    public function scopeSchoolSpecific($query)
    {
        return $query->whereNotNull('school_id');
    }

    // =================================================================
    // HELPERS
    // =================================================================

    /**
     * Get primary phone (self → primary ward → any ward)
     */
    public function getPrimaryContactPhone(): ?string
    {
        return $this->phone
            ?? $this->primaryWard()?->profile?->phone
            ?? $this->wards()->first()?->profile?->phone;
    }
}

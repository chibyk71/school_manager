<?php

namespace App\Models;

use App\Models\Student\Student;
use App\Models\Profile;
use App\Models\School;
use App\Traits\BelongsToSchool;
use App\Traits\HasAddress;
use App\Traits\HasCustomFields;
use App\Traits\HasDynamicEnum;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

/**
 * Guardian Model – Responsible Person / Ward Guardian Record (v2.0 – Production-Ready)
 *
 * Represents a guardian role for a person (linked via the central Profile model).
 * A single Profile can act as a guardian for multiple students, possibly across different schools.
 *
 * Key Architecture Rules:
 * - Personal data (name, DOB, gender, photo, phone, email, addresses) lives **only** in Profile.
 * - Guardian model stores guardian-specific metadata and relationships.
 * - Uses guardian_student pivot table with rich operational flags (is_primary_contact, can_pickup, etc.).
 * - school_id is nullable → supports tenant-wide guardians (common when one parent has children in multiple schools).
 *
 * Features / Problems Solved:
 * - Independent guardian creation (can register guardians before linking to any student).
 * - Rich guardian-student relationship metadata critical for Nigerian schools (primary contact, pickup rights, emergency priority, portal access).
 * - Full multi-tenant safety via BelongsToSchool trait (with nullable school_id).
 * - Extensibility via HasCustomFields (occupation, employer, income, legal docs, etc.).
 * - Dynamic enums ready for future fields.
 * - Clean delegation of personal attributes to Profile.
 *
 * Fits into the Student Management Module:
 * - Created standalone or during student enrollment (StudentEnrollmentService / GuardianController).
 * - Linked to students via StudentGuardianController and pivot operations.
 * - Used in frontend: GuardianFormModal.vue, AssignGuardianModal.vue, GuardiansTable.vue, Student Show → Guardians tab.
 * - Powers notifications, pickup authorization, emergency protocols, and parent portal access control.
 *
 * Important Conventions:
 * - Never manipulate Profile data directly from Guardian.
 * - All guardian-student linking logic should go through services (StudentGuardianService).
 * - school_id can be null for cross-school guardians.
 */

class Guardian extends Model
{
    use HasFactory,
        HasUuids,
        SoftDeletes,
        BelongsToSchool,
        HasCustomFields,
        HasDynamicEnum,
        Notifiable,
        HasAddress,           // Guardians often need addresses (home, work, etc.)
        HasTableQuery;

    protected $fillable = [
        'profile_id',
        'school_id',
        'notes',
    ];

    // For HasTableQuery trait – global search
    protected array $globalFilterFields = [
        'profile.first_name',
        'profile.middle_name',
        'profile.last_name',
        'profile.phone',
        'profile.email',
        'notes',
    ];

    // Dynamic enums (add fields here when you make them dynamic)
    public function getDynamicEnumProperties(): array
    {
        return []; // e.g. ['guardian_type'] if needed later
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
     * The school this guardian record belongs to (nullable for tenant-wide guardians)
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * All students (wards) this guardian is responsible for
     * Uses the rich guardian_student pivot table
     */
    public function wards(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'guardian_student')
            ->withPivot([
                'relationship',
                'is_primary_contact',
                'can_pickup',
                'can_access_portal',
                'is_emergency_contact',
                'emergency_contact_priority',
                'notes'
            ])
            ->withTimestamps();
    }

    /**
     * Primary ward (convenience accessor)
     */
    public function primaryWard(): ?Student
    {
        return $this->wards()
            ->wherePivot('is_primary_contact', true)
            ->first();
    }

    // =================================================================
    // ACCESSORS (for tables, cards, and display)
    // =================================================================

    public function getFullNameAttribute(): string
    {
        return $this->profile?->full_name ?? 'Unknown Guardian';
    }

    public function getPhotoUrlAttribute(): string
    {
        return $this->profile?->photo_url ?? asset('images/avatars/default-male.png');
    }

    public function getPhoneAttribute(): ?string
    {
        return $this->profile?->phone;
    }

    public function getEmailAttribute(): ?string
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
     * Get best contact phone with fallback logic
     */
    public function getPrimaryContactPhone(): ?string
    {
        return $this->phone
            ?? $this->primaryWard()?->profile?->phone
            ?? $this->wards()->first()?->profile?->phone;
    }
}

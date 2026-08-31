<?php

namespace App\Models\Student;

use App\Models\Guardian;
use App\Models\Profile;
use App\Models\School;
use App\Traits\BelongsToSchool;
use App\Traits\HasAddress;
use App\Traits\HasCustomFields;
use App\Traits\HasDynamicEnum;
use App\Traits\HasTableQuery;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Student Model – School-Scoped capacity record (v2.2 – Lifecycle-aligned)
 *
 * Represents a person's Student capacity within one School. Personal data (name, DOB,
 * gender, photo, addresses, etc.) lives exclusively in the linked Profile.
 *
 * Design Rules Enforced (aligned with Student Lifecycle Phases 1–4):
 * - At most one Student record per (Profile, School) — unique DB constraint.
 * - One Profile may have Student capacity in multiple schools (one row per school).
 * - Session-level registration is Enrollment (may be incomplete before Student exists).
 * - Transfer to another school: mark this Student transferred and create a NEW Student
 *   in the destination school (new Profile+School capacity). Same-school re-enrollment
 *   reuses the existing Student capacity; Enrollment rows track session participation.
 * - All session/class placement data lives in student_session_placements (not here).
 * - Status and admission_type are dynamic via HasDynamicEnum.
 *
 * Features / Problems Solved:
 * - Clean separation of identity (Profile) vs school capacity (Student) vs session
 *   registration (Enrollment).
 * - Multi-tenant safety via BelongsToSchool trait.
 * - School-specific customization of status and admission_type using HasDynamicEnum.
 * - Rich guardian management via guardian_student pivot with operational flags.
 * - Full support for advanced DataTables via HasTableQuery (with profile joins).
 *
 * Fits into the Student Management Module:
 * - Core model used by all Student services and controllers.
 * - Phase 4 finalization creates/reuses this capacity after Profile resolution.
 * - Integrates with HasAddress (on Profile), HasCustomFields, and HasDynamicEnum.
 */

class Student extends Model
{
    use HasFactory,
        HasUuids,
        SoftDeletes,
        BelongsToSchool,
        HasCustomFields,
        HasDynamicEnum,
        HasAddress,
        HasTableQuery;

    protected $fillable = [
        'profile_id',
        'school_id',
        'admission_number',
        'admission_date',
        'admission_type',
        'status',
        'status_reason',
        'status_date',
        'status_until',
        'status_changed_by',
        'previous_school',
        'previous_class',
        'previous_school_address',
        'transfer_destination',
        'transfer_certificate_number',
        'application_id',
        'notes',
    ];

    protected $casts = [
        'admission_date' => 'date',
        'status_date'    => 'date',
        'status_until'   => 'date',
    ];

    protected array $globalFilterFields = [
        'admission_number',
        'profile.first_name',
        'profile.middle_name',
        'profile.last_name',
        'profile.phone',
        'profile.email',
        'status',
        'admission_type',
    ];

    public function getDynamicEnumProperties(): array
    {
        return ['status', 'admission_type'];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(StudentApplication::class, 'application_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function admissions(): HasMany
    {
        return $this->hasMany(Admission::class);
    }

    public function sessionPlacements(): HasMany
    {
        return $this->hasMany(StudentSessionPlacement::class);
    }

    public function currentPlacement(): HasOne
    {
        return $this->hasOne(StudentSessionPlacement::class)
                    ->where('is_current', true);
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'guardian_student')
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

    public function primaryGuardian(): ?Guardian
    {
        return $this->guardians()
                    ->wherePivot('is_primary_contact', true)
                    ->first();
    }

    public function getFullNameAttribute(): string
    {
        return $this->profile?->full_name ?? 'Unknown Student';
    }

    public function getPhotoUrlAttribute(): string
    {
        return $this->profile?->photo_url ?? asset('images/avatars/default-male.png');
    }

    public function getAgeAttribute(): ?int
    {
        return $this->profile?->age;
    }

    public function getCurrentClassAttribute(): string
    {
        return $this->currentPlacement?->classLevel?->name
            ?? $this->currentPlacement?->classSection?->name
            ?? 'Not Placed';
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeAdmitted($query)
    {
        return $query->where('status', 'admitted');
    }

    public function scopeInCurrentSession($query, $sessionId)
    {
        return $query->whereHas('sessionPlacements', fn($q) =>
            $q->where('academic_session_id', $sessionId)
              ->where('is_current', true)
        );
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'enrolled']);
    }

    public function canTransfer(): bool
    {
        return in_array($this->status, ['active', 'enrolled']);
    }

    public function getPrimaryContactPhone(): ?string
    {
        return $this->primaryGuardian()?->profile?->phone
            ?? $this->guardians()->first()?->profile?->phone;
    }

    protected static function newFactory()
    {
        return StudentFactory::new();
    }
}

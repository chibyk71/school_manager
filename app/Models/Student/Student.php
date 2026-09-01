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

    /**
     * Student capacity is keyed by (school_id, profile_id), not by name.
     * SchoolScope default PARTITION BY name breaks Student queries when a school is active.
     */
    protected static function schoolScopePartitionColumns(): string|array
    {
        return 'id';
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


    protected static function booted(): void
    {
        static::updating(function (Student $student) {
            if ($student->isDirty('admission_number')) {
                $original = $student->getOriginal('admission_number');
                if (!empty($original) && $original !== $student->admission_number) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'admission_number' => 'Admission number is immutable once assigned.',
                    ]);
                }
            }
        });
    }

    protected static function newFactory()
    {
        return StudentFactory::new();
    }
}

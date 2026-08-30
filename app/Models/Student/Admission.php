<?php

namespace App\Models\Student;

use App\Models\Academic\AcademicSession;
use App\Models\Academic\ClassLevel;
use App\Models\Model;
use App\Models\School;
use App\Models\SchoolSection;
use App\Traits\BelongsToSchool;
use App\Traits\HasCustomFields;
use App\Traits\HasTableQuery;
use Database\Factories\Student\AdmissionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Admission — the school's decision/offer to give a candidate a place.
 *
 * Phase 3 domain rules:
 *   - Can exist without an Application (direct admission when applications not required)
 *   - Can exist without a Student (offer before registration / Phase 4 Enrollment)
 *   - Optional Application relationship (application-based offers)
 *   - Offered Class Level is known at offer time; section assignment is Phase 5
 *   - At most one Enrollment per Admission (DB unique on enrollments.admission_id)
 *   - Explicit status transitions: OFFERED → ACCEPTED | DECLINED | EXPIRED | CANCELLED
 *   - PENDING retained only for legacy rows; new offers start as OFFERED
 */
class Admission extends Model
{
    use BelongsToSchool,
        HasFactory,
        HasTableQuery,
        LogsActivity,
        SoftDeletes,
        HasCustomFields,
        HasUuids;

    public const STATUS_OFFERED = 'offered';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';
    /** @deprecated Legacy; prefer OFFERED for new records */
    public const STATUS_PENDING = 'pending';

    public const STATUSES = [
        self::STATUS_OFFERED,
        self::STATUS_ACCEPTED,
        self::STATUS_DECLINED,
        self::STATUS_EXPIRED,
        self::STATUS_CANCELLED,
        self::STATUS_PENDING,
    ];

    public const TRANSITIONS = [
        self::STATUS_OFFERED => [
            self::STATUS_ACCEPTED,
            self::STATUS_DECLINED,
            self::STATUS_EXPIRED,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_PENDING => [
            self::STATUS_ACCEPTED,
            self::STATUS_DECLINED,
            self::STATUS_EXPIRED,
            self::STATUS_CANCELLED,
            self::STATUS_OFFERED,
        ],
        self::STATUS_ACCEPTED => [],
        self::STATUS_DECLINED => [],
        self::STATUS_EXPIRED => [],
        self::STATUS_CANCELLED => [],
    ];

    protected $table = 'admissions';

    protected $fillable = [
        'school_id',
        'student_id',
        'application_id',
        'class_level_id',
        'school_section_id',
        'academic_session_id',
        'roll_no',
        'status',
        'offered_at',
        'acceptance_deadline',
        'accepted_at',
        'declined_at',
        'expired_at',
        'cancelled_at',
        'registration_date',
        'registration_starts_at',
        'registration_ends_at',
        'reminder_sent_at',
        'notes',
        'configs',
    ];

    protected $casts = [
        'configs' => 'array',
        'status' => 'string',
        'offered_at' => 'datetime',
        'acceptance_deadline' => 'datetime',
        'accepted_at' => 'datetime',
        'declined_at' => 'datetime',
        'expired_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'registration_date' => 'date',
        'registration_starts_at' => 'datetime',
        'registration_ends_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
    ];

    protected $hiddenTableColumns = [
        'created_at',
        'updated_at',
        'deleted_at',
        'configs',
    ];

    protected $globalFilterFields = [
        'roll_no',
        'status',
        'notes',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(StudentApplication::class, 'application_id');
    }

    public function classLevel(): BelongsTo
    {
        return $this->belongsTo(ClassLevel::class);
    }

    public function schoolSection(): BelongsTo
    {
        return $this->belongsTo(SchoolSection::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function enrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class);
    }

    protected static function booted(): void
    {
        static::saving(function (Admission $admission) {
            $admission->assertSchoolConsistency();
        });
    }

    public function assertSchoolConsistency(): void
    {
        if ($this->application_id) {
            $applicationSchoolId = StudentApplication::query()
                ->whereKey($this->application_id)
                ->value('school_id');
            if ($applicationSchoolId && $applicationSchoolId !== $this->school_id) {
                throw new \InvalidArgumentException(
                    'Admission school_id must match the related Application school_id.'
                );
            }
        }
        if ($this->student_id) {
            $studentSchoolId = Student::query()
                ->whereKey($this->student_id)
                ->value('school_id');
            if ($studentSchoolId && $studentSchoolId !== $this->school_id) {
                throw new \InvalidArgumentException(
                    'Admission school_id must match the related Student school_id.'
                );
            }
        }
        if ($this->academic_session_id) {
            $sessionSchoolId = AcademicSession::query()
                ->whereKey($this->academic_session_id)
                ->value('school_id');
            if ($sessionSchoolId && $sessionSchoolId !== $this->school_id) {
                throw new \InvalidArgumentException(
                    'Admission school_id must match the related AcademicSession school_id.'
                );
            }
        }
        if ($this->class_level_id) {
            $levelSchoolId = ClassLevel::query()
                ->whereKey($this->class_level_id)
                ->value('school_id');
            if ($levelSchoolId && $levelSchoolId !== $this->school_id) {
                throw new \InvalidArgumentException(
                    'Admission school_id must match the related ClassLevel school_id.'
                );
            }
        }
        if ($this->school_section_id) {
            $sectionSchoolId = SchoolSection::query()
                ->whereKey($this->school_section_id)
                ->value('school_id');
            if ($sectionSchoolId && $sectionSchoolId !== $this->school_id) {
                throw new \InvalidArgumentException(
                    'Admission school_id must match the related SchoolSection school_id.'
                );
            }
        }
    }

    public function getCanonicalStatusAttribute(): string
    {
        $status = $this->status ?? self::STATUS_OFFERED;
        return $status === self::STATUS_PENDING ? self::STATUS_OFFERED : $status;
    }

    public function isTerminal(): bool
    {
        return in_array($this->canonical_status, [
            self::STATUS_ACCEPTED,
            self::STATUS_DECLINED,
            self::STATUS_EXPIRED,
            self::STATUS_CANCELLED,
        ], true);
    }

    public function isOfferActive(): bool
    {
        return in_array($this->canonical_status, [self::STATUS_OFFERED, self::STATUS_PENDING], true);
    }

    public function isPastDeadline(?\DateTimeInterface $at = null): bool
    {
        if (! $this->acceptance_deadline) {
            return false;
        }
        $at = $at ?? now();
        return $this->acceptance_deadline->lte($at);
    }

    public function canBeAccepted(?\DateTimeInterface $at = null): bool
    {
        return $this->isOfferActive() && ! $this->isPastDeadline($at);
    }

    public function canBeDeclined(?\DateTimeInterface $at = null): bool
    {
        return $this->isOfferActive() && ! $this->isPastDeadline($at);
    }

    public function canExpire(?\DateTimeInterface $at = null): bool
    {
        return $this->isOfferActive() && $this->acceptance_deadline && $this->isPastDeadline($at);
    }

    public function canTransitionTo(string $to): bool
    {
        $from = $this->status ?? self::STATUS_OFFERED;
        $allowed = self::TRANSITIONS[$from] ?? [];
        return in_array($to, $allowed, true);
    }

    public function transitionTo(string $to): void
    {
        if (! $this->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => "Cannot transition admission from [{$this->status}] to [{$to}].",
            ]);
        }
        $this->status = $to;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status',
                'student_id',
                'application_id',
                'class_level_id',
                'academic_session_id',
                'offered_at',
                'acceptance_deadline',
                'accepted_at',
                'declined_at',
                'expired_at',
                'cancelled_at',
                'registration_date',
                'registration_starts_at',
                'registration_ends_at',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected static function newFactory()
    {
        return AdmissionFactory::new();
    }
}

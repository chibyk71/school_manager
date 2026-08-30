<?php

namespace App\Models\Student;

use App\Models\Academic\AcademicSession;
use App\Models\Model;
use App\Models\School;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Database\Factories\Student\EnrollmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Enrollment — actual registration of a Student in a School for an Academic Session.
 *
 * Phase 1 domain foundation only.
 *
 * Status vocabulary:
 *   draft | in_progress | active | withdrawn | transferred_out | completed
 */
class Enrollment extends Model
{
    use HasFactory,
        HasUuids,
        SoftDeletes,
        BelongsToSchool,
        HasTableQuery;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_WITHDRAWN = 'withdrawn';
    public const STATUS_TRANSFERRED_OUT = 'transferred_out';
    public const STATUS_COMPLETED = 'completed';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_IN_PROGRESS,
        self::STATUS_ACTIVE,
        self::STATUS_WITHDRAWN,
        self::STATUS_TRANSFERRED_OUT,
        self::STATUS_COMPLETED,
    ];

    protected $table = 'enrollments';

    protected $fillable = [
        'school_id',
        'student_id',
        'academic_session_id',
        'admission_id',
        'status',
        'started_at',
        'activated_at',
        'withdrawn_at',
        'transferred_out_at',
        'completed_at',
        'notes',
        'meta',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'activated_at' => 'datetime',
        'withdrawn_at' => 'datetime',
        'transferred_out_at' => 'datetime',
        'completed_at' => 'datetime',
        'meta' => 'array',
        'status' => 'string',
    ];

    protected array $globalFilterFields = [
        'status',
        'notes',
    ];

    protected $hiddenTableColumns = [
        'meta',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    protected static function booted(): void
    {
        static::saving(function (Enrollment $enrollment) {
            $enrollment->assertSchoolConsistency();
        });
    }

    /**
     * Prevent cross-school relationships at the domain layer.
     */
    public function assertSchoolConsistency(): void
    {
        if ($this->student_id) {
            $studentSchoolId = Student::query()
                ->whereKey($this->student_id)
                ->value('school_id');

            if ($studentSchoolId && $studentSchoolId !== $this->school_id) {
                throw new \InvalidArgumentException(
                    'Enrollment school_id must match the related Student school_id.'
                );
            }
        }

        if ($this->academic_session_id) {
            $sessionSchoolId = AcademicSession::query()
                ->whereKey($this->academic_session_id)
                ->value('school_id');

            if ($sessionSchoolId && $sessionSchoolId !== $this->school_id) {
                throw new \InvalidArgumentException(
                    'Enrollment school_id must match the related AcademicSession school_id.'
                );
            }
        }

        if ($this->admission_id) {
            $admissionSchoolId = Admission::query()
                ->whereKey($this->admission_id)
                ->value('school_id');

            if ($admissionSchoolId && $admissionSchoolId !== $this->school_id) {
                throw new \InvalidArgumentException(
                    'Enrollment school_id must match the related Admission school_id.'
                );
            }
        }
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeIncomplete($query)
    {
        return $query->whereIn('status', [self::STATUS_DRAFT, self::STATUS_IN_PROGRESS]);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isIncomplete(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_IN_PROGRESS], true);
    }

    protected static function newFactory()
    {
        return EnrollmentFactory::new();
    }
}

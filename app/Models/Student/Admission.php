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
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Admission — the school's decision/offer to give a candidate a place.
 *
 * Phase 1 domain rules:
 *   - Can exist without an Application (direct admission)
 *   - Can exist without a Student (offer before registration)
 *   - Optional Application relationship
 *   - Offered Class Level is known at offer time
 *   - At most one Enrollment per Admission (DB unique on enrollments.admission_id)
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
    public const STATUS_PENDING = 'pending';

    public const STATUSES = [
        self::STATUS_OFFERED,
        self::STATUS_ACCEPTED,
        self::STATUS_DECLINED,
        self::STATUS_EXPIRED,
        self::STATUS_CANCELLED,
        self::STATUS_PENDING,
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
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected static function newFactory()
    {
        return AdmissionFactory::new();
    }
}

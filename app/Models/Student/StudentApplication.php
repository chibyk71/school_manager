<?php

namespace App\Models\Student;

use App\Helpers\IdGenerator;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\ClassLevel;
use App\Models\School;
use App\Models\SchoolSection;
use App\Models\User;
use App\Traits\BelongsToSchool;
use App\Traits\HasCustomFields;
use App\Traits\HasDynamicEnum;
use App\Traits\HasTableQuery;
use Database\Factories\Student\StudentApplicationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * StudentApplication – pre-admission candidate request (Phase 2).
 *
 * Independent of Profile, User, Student, Admission, and Enrollment.
 * Approval of an Application does NOT create an Admission or Student.
 * Integrates with existing Custom Fields engine via HasCustomFields.
 */
class StudentApplication extends Model
{
    use HasFactory,
        HasUuids,
        SoftDeletes,
        BelongsToSchool,
        HasCustomFields,
        HasDynamicEnum,
        HasTableQuery;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_WITHDRAWN = 'withdrawn';

    /** @deprecated Legacy alias */
    public const STATUS_PENDING = 'pending';

    public const SOURCE_PUBLIC = 'public_portal';
    public const SOURCE_STAFF = 'admin_direct';

    /** Application fee payment state (Finance boundary – not a parallel ledger). */
    public const FEE_NOT_REQUIRED = 'not_required';
    public const FEE_UNPAID = 'unpaid';
    public const FEE_PAID = 'paid';
    public const FEE_WAIVED = 'waived';

    public const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_SUBMITTED, self::STATUS_WITHDRAWN],
        self::STATUS_SUBMITTED => [
            self::STATUS_UNDER_REVIEW,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_WITHDRAWN,
        ],
        self::STATUS_UNDER_REVIEW => [
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_WITHDRAWN,
        ],
        self::STATUS_APPROVED => [],
        self::STATUS_REJECTED => [],
        self::STATUS_WITHDRAWN => [],
    ];

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
        'fee_payment_status',
        'fee_payment_reference',
        'fee_paid_at',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'fee_paid_at' => 'datetime',
        'guardians_data' => 'array',
        'documents' => 'array',
        'custom_data' => 'array',
        'status' => 'string',
        'source' => 'string',
        'gender' => 'string',
        'religion' => 'string',
        'blood_group' => 'string',
        'fee_payment_status' => 'string',
    ];

    /** Never expose token in serialization by default. */
    protected $hidden = [
        'application_token',
    ];

    protected array $globalFilterFields = [
        'application_number',
        'first_name',
        'last_name',
        'phone',
        'email',
        'status',
        'source',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $application) {
            $application->assertSchoolConsistency();
            $application->normalizeLegacyStatus();
        });
    }

    public function getDynamicEnumProperties(): array
    {
        return ['status', 'gender', 'religion', 'blood_group'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function schoolSection(): BelongsTo
    {
        return $this->belongsTo(SchoolSection::class);
    }

    public function classLevel(): BelongsTo
    {
        return $this->belongsTo(ClassLevel::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function admissions(): HasMany
    {
        return $this->hasMany(Admission::class, 'application_id');
    }

    public function scopeSubmitted($query)
    {
        return $query->whereIn('status', [self::STATUS_SUBMITTED, self::STATUS_PENDING]);
    }

    public function scopeUnderReview($query)
    {
        return $query->where('status', self::STATUS_UNDER_REVIEW);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeFromPublicPortal($query)
    {
        return $query->where('source', self::SOURCE_PUBLIC);
    }

    public function scopeForSession($query, $sessionId)
    {
        return $query->where('academic_session_id', $sessionId);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
    }

    public function getCanonicalStatusAttribute(): string
    {
        $status = $this->status ?? self::STATUS_DRAFT;

        return $status === self::STATUS_PENDING ? self::STATUS_SUBMITTED : $status;
    }

    /**
     * Whether the configured application fee is considered satisfied.
     * Application exists ≠ fee paid.
     */
    public function isApplicationFeeSatisfied(): bool
    {
        $status = $this->fee_payment_status ?? self::FEE_NOT_REQUIRED;

        return in_array($status, [self::FEE_NOT_REQUIRED, self::FEE_PAID, self::FEE_WAIVED], true);
    }

    public function canTransitionTo(string $to): bool
    {
        $from = $this->canonical_status;
        $allowed = self::TRANSITIONS[$from] ?? [];

        return in_array($to, $allowed, true);
    }

    public function transitionTo(string $to): void
    {
        if (! $this->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => "Cannot transition application from [{$this->canonical_status}] to [{$to}].",
            ]);
        }

        $this->status = $to;
    }

    public function assignApplicationNumber(?School $school = null): string
    {
        if (! empty($this->application_number)) {
            return $this->application_number;
        }

        $school = $school ?? $this->school ?? (function_exists('GetSchoolModel') ? GetSchoolModel() : null);

        try {
            $number = IdGenerator::generate('application', $school, now()->year);
        } catch (\Throwable $e) {
            Log::warning('IdGenerator failed for application; using fallback sequence', [
                'error' => $e->getMessage(),
            ]);
            $number = 'APP-'.now()->year.'-'.strtoupper(Str::random(8));
        }

        $this->application_number = $number;

        return $number;
    }

    public function assignToken(): string
    {
        if (! empty($this->application_token)) {
            return $this->application_token;
        }

        $this->application_token = Str::random(64);

        return $this->application_token;
    }

    public function findLikelyDuplicates(): \Illuminate\Database\Eloquent\Collection
    {
        $query = static::query()
            ->where('school_id', $this->school_id)
            ->when($this->id, fn ($q) => $q->where('id', '!=', $this->id))
            ->when($this->academic_session_id, fn ($q) => $q->where('academic_session_id', $this->academic_session_id))
            ->where(function ($q) {
                $q->where(function ($inner) {
                    $inner->where('first_name', $this->first_name)
                        ->where('last_name', $this->last_name)
                        ->when($this->date_of_birth, fn ($qq) => $qq->whereDate('date_of_birth', $this->date_of_birth));
                });

                if ($this->email) {
                    $q->orWhere('email', $this->email);
                }
                if ($this->phone) {
                    $q->orWhere('phone', $this->phone);
                }
            });

        return $query->limit(20)->get();
    }

    protected function assertSchoolConsistency(): void
    {
        if ($this->academic_session_id) {
            $sessionSchoolId = AcademicSession::query()
                ->whereKey($this->academic_session_id)
                ->value('school_id');

            if ($sessionSchoolId && $this->school_id && $sessionSchoolId !== $this->school_id) {
                throw ValidationException::withMessages([
                    'academic_session_id' => 'Academic session does not belong to the same school as this application.',
                ]);
            }
        }

        if ($this->class_level_id) {
            $levelSchoolId = ClassLevel::query()
                ->whereKey($this->class_level_id)
                ->value('school_id');

            if ($levelSchoolId && $this->school_id && $levelSchoolId !== $this->school_id) {
                throw ValidationException::withMessages([
                    'class_level_id' => 'Class level does not belong to the same school as this application.',
                ]);
            }
        }
    }

    protected function normalizeLegacyStatus(): void
    {
        if ($this->status === self::STATUS_PENDING) {
            $this->status = self::STATUS_SUBMITTED;
        }
    }

    protected static function newFactory()
    {
        return StudentApplicationFactory::new();
    }
}

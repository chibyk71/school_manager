<?php

namespace App\Models\Student;

use App\Models\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-enrollment state of a requirement definition.
 *
 * Status: pending | satisfied | waived
 */
class EnrollmentRequirementInstance extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_SATISFIED = 'satisfied';
    public const STATUS_WAIVED = 'waived';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_SATISFIED,
        self::STATUS_WAIVED,
    ];

    protected $table = 'enrollment_requirement_instances';

    protected $fillable = [
        'enrollment_id',
        'definition_id',
        'status',
        'satisfied_at',
        'satisfied_by',
        'waived_at',
        'waived_by',
        'waiver_reason',
        'document_id',
        'external_reference',
        'meta',
    ];

    protected $casts = [
        'satisfied_at' => 'datetime',
        'waived_at' => 'datetime',
        'meta' => 'array',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(EnrollmentRequirementDefinition::class, 'definition_id');
    }

    public function satisfiedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'satisfied_by');
    }

    public function waivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waived_by');
    }

    protected static function booted(): void
    {
        static::saving(function (EnrollmentRequirementInstance $instance) {
            $instance->assertSchoolConsistency();
        });
    }

    /**
     * Definition must belong to the same School as the Enrollment.
     * Uses existing school-scoped Definition + Enrollment relationship (no tenant layer).
     */
    public function assertSchoolConsistency(): void
    {
        if (! $this->enrollment_id || ! $this->definition_id) {
            return;
        }

        $enrollmentSchoolId = Enrollment::query()
            ->whereKey($this->enrollment_id)
            ->value('school_id');

        $definitionSchoolId = EnrollmentRequirementDefinition::query()
            ->whereKey($this->definition_id)
            ->value('school_id');

        if ($enrollmentSchoolId && $definitionSchoolId && $enrollmentSchoolId !== $definitionSchoolId) {
            throw new \InvalidArgumentException(
                'Requirement definition school_id must match the enrollment school_id.'
            );
        }
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isSatisfied(): bool
    {
        return $this->status === self::STATUS_SATISFIED;
    }

    public function isWaived(): bool
    {
        return $this->status === self::STATUS_WAIVED;
    }

    public function isComplete(): bool
    {
        return in_array($this->status, [self::STATUS_SATISFIED, self::STATUS_WAIVED], true);
    }
}

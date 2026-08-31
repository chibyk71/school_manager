<?php

namespace App\Models\Student;

use App\Models\Model;
use App\Models\School;
use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * School-scoped definition of an enrollment prerequisite.
 *
 * Types: FORM | DOCUMENT | PAYMENT | CHECKLIST | ACKNOWLEDGEMENT | MANUAL
 */
class EnrollmentRequirementDefinition extends Model
{
    use HasUuids, SoftDeletes, BelongsToSchool;

    public const TYPE_FORM = 'FORM';
    public const TYPE_DOCUMENT = 'DOCUMENT';
    public const TYPE_PAYMENT = 'PAYMENT';
    public const TYPE_CHECKLIST = 'CHECKLIST';
    public const TYPE_ACKNOWLEDGEMENT = 'ACKNOWLEDGEMENT';
    public const TYPE_MANUAL = 'MANUAL';

    public const TYPES = [
        self::TYPE_FORM,
        self::TYPE_DOCUMENT,
        self::TYPE_PAYMENT,
        self::TYPE_CHECKLIST,
        self::TYPE_ACKNOWLEDGEMENT,
        self::TYPE_MANUAL,
    ];

    protected $table = 'enrollment_requirement_definitions';

    protected $fillable = [
        'school_id',
        'code',
        'name',
        'description',
        'type',
        'is_required',
        'is_active',
        'sort_order',
        'config',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'config' => 'array',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function instances(): HasMany
    {
        return $this->hasMany(EnrollmentRequirementInstance::class, 'definition_id');
    }
}

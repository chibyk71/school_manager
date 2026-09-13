<?php

namespace App\Models\Academic;

use App\Models\School;
use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Current dependency of a resource on an Academic Session and optional Term.
 *
 * Not a historical ledger: soft-deleted / force-deleted resources remove their row.
 * Academic does not inspect the business state of the resource.
 */
class AcademicPeriodUsage extends Model
{
    use HasUuids;
    use BelongsToSchool;

    protected $table = 'academic_period_usages';

    protected $fillable = [
        'school_id',
        'academic_session_id',
        'term_id',
        'resource_type',
        'resource_id',
    ];

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'term_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function resource(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'resource_type', 'resource_id');
    }

    protected static function schoolScopePartitionColumns(): string|array
    {
        return 'id';
    }
}

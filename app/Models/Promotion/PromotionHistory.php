<?php
// app/Models/Promotion/PromotionHistory.php

namespace App\Models\Promotion;

use App\Models\Academic\AcademicSession;
use App\Models\Academic\ClassSection;
use App\Models\Academic\Student;
use App\Models\User;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Permanent, immutable record of a completed promotion.
 *
 * Used for transcripts, certificates, and government reporting.
 */
class PromotionHistory extends Model
{
    use HasFactory, LogsActivity, HasTableQuery;

    protected $fillable = [
        'student_id',
        'from_academic_session_id',
        'to_academic_session_id',
        'from_class_section_id',
        'to_class_section_id',
        'outcome',
        'remarks',
        'executed_by',
        'executed_at',
    ];

    protected $casts = [
        'executed_at' => 'datetime',
    ];

    protected array $hiddenTableColumns = [
        'executed_by',
    ];

    protected array $globalFilterFields = [
        'outcome',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['outcome', 'remarks'])
            ->dontSubmitEmptyLogs();
    }

    // ────────────────────────────────────────────────────────────────
    // Relationships
    // ────────────────────────────────────────────────────────────────

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function fromSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'from_academic_session_id');
    }

    public function toSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'to_academic_session_id');
    }

    public function fromSection(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class, 'from_class_section_id');
    }

    public function toSection(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class, 'to_class_section_id');
    }

    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }
}
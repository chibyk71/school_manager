<?php

namespace App\Models\Student;

use App\Models\Academic\ClassLevel;
use App\Models\Academic\ClassSection;
use App\Models\Academic\AcademicSession;
use App\Traits\HasDynamicEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * StudentSessionPlacement Model – Academic Placement Record (v2.0 – Production-Ready)
 *
 * Integer auto-increment primary key (matches migration; NOT UUID).
 * registration_number_histories.placement_id is unsignedBigInteger referencing this id.
 *
 * This model tracks where a student is academically placed in a specific academic session.
 * Mid-session section moves end the current row and create a new one (history preserved).
 */

class StudentSessionPlacement extends Model
{
    // Integer auto-increment PK (matches migration; NOT UUID).
    // registration_number_histories.placement_id is unsignedBigInteger referencing this id.
    use HasFactory,
        HasDynamicEnum;

    protected $fillable = [
        'student_id',
        'enrollment_id',
        'academic_session_id',
        'class_level_id',
        'class_section_id',
        'registration_number',
        'enrolled_at',
        'left_at',
        'is_current',
        'promotion_outcome',
        'promotion_batch_id',
        'notes',
        'capacity_override_used',
        'placed_by',
        'meta',
    ];

    protected $casts = [
        'enrolled_at' => 'date',
        'left_at' => 'date',
        'is_current' => 'boolean',
        'capacity_override_used' => 'boolean',
        'promotion_outcome' => 'string',
        'meta' => 'array',
    ];

    public function getDynamicEnumProperties(): array
    {
        return ['promotion_outcome'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function classLevel(): BelongsTo
    {
        return $this->belongsTo(ClassLevel::class);
    }

    public function classSection(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class);
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function scopeForSession($query, $sessionId)
    {
        return $query->where('academic_session_id', $sessionId);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('left_at');
    }

    public function markAsCurrent(): void
    {
        $this->update(['is_current' => true]);
    }

    public function markAsLeft(\Carbon\Carbon $date = null): void
    {
        $this->update([
            'left_at' => $date ?? now()->toDateString(),
            'is_current' => false,
        ]);
    }

    public function isActive(): bool
    {
        return $this->left_at === null;
    }

    public function getDisplayNameAttribute(): string
    {
        $level = $this->classLevel?->name ?? 'Unknown Level';
        $section = $this->classSection?->name ? " ({$this->classSection->name})" : '';

        return $level . $section;
    }
}

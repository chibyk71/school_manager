<?php

namespace App\Models\Student;

use App\Models\Academic\ClassLevel;
use App\Models\Academic\ClassSection;
use App\Models\Academic\AcademicSession;
use App\Traits\HasDynamicEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * StudentSessionPlacement Model – Academic Placement Record (v2.0 – Production-Ready)
 *
 * This model tracks where a student is academically placed in a specific academic session.
 * It answers the question: "In which class level and section (arm) was this student enrolled
 * during a particular session?"
 *
 * Why This Model Exists Separately:
 * - One Student record can have many placements across different academic sessions (full history).
 * - Supports promotion, repetition, transfer-in, and re-admission workflows cleanly.
 * - Allows mid-session changes (e.g., moving from JSS 1A to JSS 1B) by updating the existing record.
 * - Enables powerful queries like "Show all students in SSS 2 Science this session".
 *
 * Key Design Decisions:
 * - No school_id column — school context is inherited through the Student (via BelongsToSchool trait).
 * - class_level_id is required; class_section_id is nullable (arm can be assigned later).
 * - is_current flag is denormalized for fast current-placement lookups.
 * - promotion_outcome is a string (supports HasDynamicEnum for school-specific promotion types).
 *
 * Features / Problems Solved:
 * - Complete academic history without mutating the main Student record.
 * - Clean promotion workflow: new placement created for next session; old one preserved.
 * - High-performance queries for class lists, attendance, report cards, and fees.
 * - Flexible promotion outcomes via HasDynamicEnum.
 * - Soft-delete friendly (though rarely deleted — history is valuable).
 *
 * Fits into the Student Management Module:
 * - Heavily used by StudentPlacementService, StudentEnrollmentService, and future PromotionService.
 * - Accessed via Student::sessionPlacements() and Student::currentPlacement().
 * - Powers frontend components: PlacementInfo.vue, class lists in DataTables, Student Show → Academic tab.
 * - Works with HasTableQuery for advanced placement-based reporting.
 *
 * Relationship Flow:
 *   Profile → Student → StudentSessionPlacement → ClassLevel / ClassSection / AcademicSession
 */

class StudentSessionPlacement extends Model
{
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

    // For HasDynamicEnum trait
    // promotion_outcome can be customized per school (e.g. 'promoted', 'repeated', 'probation', 'transferred_in', etc.)
    public function getDynamicEnumProperties(): array
    {
        return ['promotion_outcome'];
    }

    // =================================================================
    // RELATIONSHIPS
    // =================================================================

    /**
     * The student this placement belongs to
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * The academic session this placement belongs to
     */
    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /**
     * The class level (e.g., JSS 1, Primary 4, SSS 2)
     */
    public function classLevel(): BelongsTo
    {
        return $this->belongsTo(ClassLevel::class);
    }

    /**
     * The specific class section/arm (e.g., JSS 1A, Primary 4B) — can be null
     */
    public function classSection(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class);
    }

    // =================================================================
    // SCOPES
    // =================================================================

    /**
     * Current active placements only
     */
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    /**
     * Placements for a specific academic session
     */
    public function scopeForSession($query, $sessionId)
    {
        return $query->where('academic_session_id', $sessionId);
    }

    /**
     * Active placements (not yet left)
     */
    public function scopeActive($query)
    {
        return $query->whereNull('left_at');
    }

    // =================================================================
    // HELPERS
    // =================================================================

    /**
     * Mark this placement as the student's current one.
     * Should be called through StudentPlacementService to maintain consistency.
     */
    public function markAsCurrent(): void
    {
        // Unset other current placements for this student (handled in service)
        $this->update(['is_current' => true]);
    }

    /**
     * Mark this placement as ended (student left this placement)
     */
    public function markAsLeft(\Carbon\Carbon $date = null): void
    {
        $this->update([
            'left_at' => $date ?? now()->toDateString(),
            'is_current' => false,
        ]);
    }

    /**
     * Check if this placement is still active
     */
    public function isActive(): bool
    {
        return $this->left_at === null;
    }

    /**
     * Get a readable display name for this placement
     */
    public function getDisplayNameAttribute(): string
    {
        $level = $this->classLevel?->name ?? 'Unknown Level';
        $section = $this->classSection?->name ? " ({$this->classSection->name})" : '';

        return $level . $section;
    }
}

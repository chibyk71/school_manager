<?php

namespace App\Models\Academic;

use App\Models\ExamResult; // ← Add this if you have an ExamResult model
use App\Traits\BelongsToSchool;
use App\Traits\BelongsToSections;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Grade Model – Represents a single performance band in a school's grading scale
 * (e.g., "A" = 80–100, "B+" = 75–79, etc.)
 *
 * Core Responsibilities / Features Implemented:
 * ────────────────────────────────────────────────────────────────────────────────────────────────
 * • Defines score ranges (min_score → max_score) for automatic grade assignment
 * • Supports school-wide grades + optional section-specific overrides
 * • Multi-tenant isolation via BelongsToSchool trait
 * • Dynamic DataTable querying via HasTableQuery trait
 * • Soft-deletes + activity logging (Spatie) for audit trail
 * • Usage protection: prevents modification/deletion if grade is referenced in results
 *
 * How it fits into the Grades Module:
 * ────────────────────────────────────────────────────────────────────────────────────────────────
 * • Central domain model – used by GradeController, GradeService, validation rules
 * • Powers grading scale UI (DataTable + modals)
 * • Referenced in ExamResult / Assessment / ReportCard logic for grade lookup
 * • Activity log provides immutable history (replaces need for custom history table)
 *
 * Key Improvements in this version:
 * • Better PHPDoc + property annotations
 * • Enhanced activity logging (human-readable events, ignore updated_at noise)
 * • Added usage-checking scopes & methods (critical safety feature)
 * • Helper accessors (range display, isUsed flag)
 * • Prepared for future GPA/weight extensions
 */
class Grade extends Model
{
    use HasFactory;
    use BelongsToSchool;
    use BelongsToSections;
    use HasTableQuery;
    use SoftDeletes;
    use LogsActivity;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'grades';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'school_id',
        'name',
        'code',
        'min_score',
        'max_score',
        'remark',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'min_score' => 'integer',
        'max_score' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Columns excluded from DataTable search/sort/filter (security + clutter reduction).
     *
     * @var array<string>
     */
    protected array $hiddenTableColumns = [
        'school_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Fields used for global free-text search in DataTable.
     *
     * @var array<string>
     */
    protected array $globalFilterFields = [
        'name',
        'code',
        'remark',
    ];

    // ─── ACTIVITY LOGGING ─────────────────────────────────────────────────────────────

    /**
     * Configure detailed activity logging via Spatie.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('grade')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at']) // ignore timestamp touches
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(function (string $eventName): string {
                $name = $this->name ?? 'unnamed';
                $code = $this->code ?? '—';

                return match ($eventName) {
                    'created' => "Created grade \"{$name}\" ({$code})",
                    'updated' => "Updated grade \"{$name}\" ({$code})",
                    'deleted' => "Deleted grade \"{$name}\" ({$code})",
                    'restored' => "Restored grade \"{$name}\" ({$code})",
                    default => "Grade \"{$name}\" ({$code}) was {$eventName}",
                };
            });
    }

    // ─── SCOPES ──────────────────────────────────────────────────────────────────────

    /**
     * Scope: Grades that are currently in use (referenced by any ExamResult).
     *
     * Usage: Grade::inUse()->get(); or Grade::notInUse()->get();
     */
    public function scopeInUse(Builder $query): Builder
    {
        return $query->whereExists(function ($sub) {
            $sub->select(\DB::raw(1))
                ->from('exam_results') // ← adjust table name if different
                ->whereColumn('exam_results.grade_id', 'grades.id');
        });
    }

    public function scopeNotInUse(Builder $query): Builder
    {
        return $query->whereDoesntHave('examResults'); // if you add relation below
    }

    // ─── HELPERS & ACCESSORS ─────────────────────────────────────────────────────────

    /**
     * Get a human-readable display of the score range (e.g. "80 – 100").
     *
     * @return string
     */
    public function getRangeAttribute(): string
    {
        return "{$this->min_score} – {$this->max_score}";
    }

    /**
     * Check if this grade is currently referenced in any student results.
     * TODO: Implement this method once you have an ExamResult model with a grade_id foreign key.
     * @return bool
     */
    public function isUsed(): bool
    {
        return $this->examResults()->exists(); // if relation added
        // or: return ExamResult::where('grade_id', $this->id)->exists();
    }

    // ─── FUTURE-PROOFING / EXTENSIONS ────────────────────────────────────────────────

    /**
     * Placeholder relation – add when you implement ExamResult model
     * TODO: Create ExamResult model with grade_id foreign key, then uncomment this method
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    // public function examResults(): \Illuminate\Database\Eloquent\Relations\HasMany
    // {
    //     return $this->hasMany(ExamResult::class, 'grade_id');
    // }

    /**
     * Stub: Check if this grade overlaps with another in the same section.
     * (Will be used by validation rule later)
     */
    public function overlapsWith(Grade $other): bool
    {
        if ($this->school_section_id !== $other->school_section_id) {
            return false;
        }

        return !(
            $this->max_score < $other->min_score ||
            $this->min_score > $other->max_score
        );
    }
}

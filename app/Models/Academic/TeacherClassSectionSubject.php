<?php

namespace App\Models\Academic;

use App\Models\Employee\Staff;
use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * TeacherClassSectionSubject — teacher-subject assignment within a class section.
 *
 * Records which teacher teaches which subject in which class section, and
 * optionally what role they have (e.g., subject_teacher, co_teacher).
 *
 * ── What This Model Owns ─────────────────────────────────────────────────────
 * This is a first-class model (not a plain pivot) because:
 * 1. It has its own audit log (LogsActivity)
 * 2. It carries additional data beyond the FK join (role, soft deletes)
 * 3. It is queried directly by Timetable and Results modules
 * 4. It supports soft delete to preserve historical assignment records
 *    needed for result lookups referencing past assignments
 *
 * ── Role Values ──────────────────────────────────────────────────────────────
 * Common role values (stored as string, not enum — customizable per school):
 *   'subject_teacher'  — primary teacher, full result entry rights (default)
 *   'co_teacher'       — co-teaching arrangement, shared rights
 *   'cover_teacher'    — temporary cover, limited rights
 *   'supervisor'       — oversight role (e.g., HOD observing)
 *   null               — no specific role; treated as subject_teacher by convention
 *
 * ── Session Scoping ──────────────────────────────────────────────────────────
 * This table does NOT have academic_session_id. Assignments are configured
 * at the start of each session and are managed (cleared/re-assigned) as part
 * of the session setup workflow. The Timetable module owns session scoping.
 * SoftDeletes preserves historical assignments for result lookups.
 *
 * ── Module Ownership ─────────────────────────────────────────────────────────
 * MANAGED by:  ClassSection module (Subject Assignments tab on section detail)
 * READ by:     Timetable module, Results module (authorization checks),
 *              Staff/HR module ("what does this teacher teach?")
 *
 * ── Properties ───────────────────────────────────────────────────────────────
 * @property int         $id
 * @property string      $school_id         UUID — multi-tenant anchor (denormalized)
 * @property string      $teacher_id        UUID → staff
 * @property string      $class_section_id  UUID → class_sections
 * @property string      $subject_id        UUID → subjects
 * @property string|null $role              Assignment role
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * ── Relationships ─────────────────────────────────────────────────────────────
 * @property-read Staff        $teacher
 * @property-read ClassSection $classSection
 * @property-read Subject      $subject
 */
class TeacherClassSectionSubject extends Model
{
    use HasFactory;
    use BelongsToSchool;
    use SoftDeletes;
    use LogsActivity;

    protected $table = 'teacher_class_section_subjects';

    protected $keyType = 'int'; // bigIncrements primary key
    public $incrementing = true;

    protected $fillable = [
        'school_id',
        'teacher_id',
        'class_section_id',
        'subject_id',
        'role',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ──────────────────────────────────────────────────────────────────────────
    // Activity Log
    // ──────────────────────────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(
                fn (string $eventName) =>
                    "Subject assignment for \"{$this->subject?->name}\" in section " .
                    "\"{$this->classSection?->display_name_computed}\" was {$eventName}"
            );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * The teacher (staff member) for this assignment.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'teacher_id');
    }

    /**
     * The class section this assignment belongs to.
     */
    public function classSection(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class, 'class_section_id');
    }

    /**
     * The subject being taught in this assignment.
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Filter assignments by role.
     * e.g., $query->forRole('subject_teacher')
     */
    public function scopeForRole(Builder $query, string $role): Builder
    {
        return $query->where('role', $role);
    }

    /**
     * Filter to primary subject teachers only (role = 'subject_teacher' or null).
     * Used by Results module to determine result entry authorization.
     */
    public function scopePrimaryTeachers(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('role', 'subject_teacher')
                ->orWhereNull('role');
        });
    }

    /**
     * Filter assignments for a specific teacher.
     * Used by the Staff module to show "what does this teacher teach?"
     */
    public function scopeForTeacher(Builder $query, string $teacherId): Builder
    {
        return $query->where('teacher_id', $teacherId);
    }

    /**
     * Filter assignments for a specific class section.
     */
    public function scopeForSection(Builder $query, string $classSectionId): Builder
    {
        return $query->where('class_section_id', $classSectionId);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * The effective role label for display.
     * Returns "Subject Teacher" when role is null (the default convention).
     */
    public function getEffectiveRoleLabel(): string
    {
        $role = $this->role ?? 'subject_teacher';

        return match ($role) {
            'subject_teacher' => 'Subject Teacher',
            'co_teacher'      => 'Co-Teacher',
            'cover_teacher'   => 'Cover Teacher',
            'supervisor'      => 'Supervisor',
            default           => ucwords(str_replace('_', ' ', $role)),
        };
    }
}

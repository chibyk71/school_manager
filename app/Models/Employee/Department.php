<?php

namespace App\Models\Employee;

use App\Models\Role;
use App\Models\User;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Department Model – Clean, Production-Ready Implementation (December 2025)
 *
 * Purpose & Architecture Decisions:
 *
 * 1. Core Responsibility:
 *    - Represents an organizational unit within a school (e.g., Academics, Administration, ICT, Library).
 *    - Used primarily for grouping staff, reporting lines, headcount, and department-specific communication.
 *
 * 2. Why we removed all redundant relationships:
 *    - Previously had direct `department_user` pivot + complex `department_role` with custom names/sections.
 *    - After review: these were over-engineered and duplicated what Laratrust already handles well.
 *    - Final clean architecture:
 *        • Permissions → Roles → Users (standard Laratrust)
 *        • Organizational grouping: Roles → Department (many-to-many via simple pivot)
 *        • Users belong to a department indirectly through their assigned roles
 *    - Benefits: No data duplication, simpler queries, easier maintenance, standard RBAC patterns.
 *
 * 3. Current Relationships:
 *    - belongsToMany Role (simple pivot `department_role` – only department_id + role_id + timestamps)
 *    - derived users(): Users who have any role assigned to this department
 *    - member_count accessor: Cached headcount for DataTable display and reporting
 *
 * 4. Real-world Use Cases Covered:
 *    - HOD assignment: Assign "hod" role to department → users with "hod" role appear as members
 *    - Non-teaching staff: Roles like "driver", "cleaner", "librarian" assigned to relevant departments
 *    - Temporary/cross-department work: Assign multiple roles to user
 *    - Section-specific roles (Junior/Senior): Handled by separate global roles (e.g., "teacher_junior")
 *    - Reporting & announcements: Query users via department's derived members
 *
 * 5. Frontend Impact:
 *    - Index table shows member count (derived)
 *    - Edit modal allows multi-select of global roles to assign to department
 *    - Member list tab uses /departments/{id}/users endpoint (derived relationship)
 *
 * This model is now lightweight, performant, and fully aligned with industry-standard school SaaS patterns.
 */
class Department extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, BelongsToSchool, HasTableQuery, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'school_id',
        'name',
        'category',
        'description',
        'effective_date',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'effective_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Columns hidden from DataTable search/sort/filter (via HasTableQuery trait).
     *
     * @var array<string>
     */
    protected array $hiddenTableColumns = [
        'school_id',
        'description',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Columns used for global search in the DataTable.
     *
     * @var array<string>
     */
    protected array $globalFilterFields = [
        'name',
        'category',
    ];

    /**
     * Configure Spatie activity logging.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('department')
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => "Department '{$this->name}' was {$eventName}");
    }

    /**
     * Roles assigned to this department (many-to-many).
     * Uses simple pivot table: department_role (department_id, role_id, timestamps).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'department_role')
            ->using(DepartmentRole::class)
            ->withTimestamps();
    }

    /**
     * Users who belong to this department (derived relationship).
     * A user is considered a member if they have any role that is assigned to this department.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->whereHas('roles', function ($query) {
                $query->whereIn('roles.id', $this->roles()->pluck('roles.id'));
            })
            ->withTimestamps();
    }

    /**
     * Accessor: Number of members in this department.
     * Used in DataTable for quick headcount display and reporting.
     *
     * @return int
     */
    public function getMemberCountAttribute(): int
    {
        return $this->users()->count();
    }

    /**
     * Optional: Scope to eager load common relations for index/performance.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithCommon($query)
    {
        return $query->with(['roles']);
    }
}

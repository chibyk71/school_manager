<?php

namespace App\Models\Employee;

use App\Traits\BelongsToSections;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * DepartmentRole – Custom Pivot Model for the department_role table
 *
 * Purpose & Design Decisions (as of December 2025):
 *
 * 1. Why this is a full Pivot model (extends Pivot) instead of a simple pivot table:
 *    - We need extra metadata beyond the basic department_id + role_id foreign keys.
 *    - Specifically, we want to support optional scoping to one or more School Sections
 *      (e.g., "HOD – Junior Section", "Subject Teacher – Senior Section").
 *    - Using the BelongsToSections trait gives us a polymorphic many-to-many relationship
 *      between a DepartmentRole and SchoolSection(s), allowing the same role (e.g. "teacher")
 *      to exist in multiple sections within the same department without duplicating rows.
 *
 * 2. Why we kept a dedicated model even after simplifying the architecture:
 *    - Standard Laratrust role assignment (User ↔ Role) handles permissions perfectly.
 *    - Department ↔ Role assignment handles functional grouping.
 *    - The additional section scoping provides real-world flexibility for schools that
 *      separate Junior/Secondary, Primary, Boarding, etc.
 *    - This avoids creating duplicate global roles like "teacher_junior" and "teacher_senior".
 *
 * 3. Current relationships:
 *    - DepartmentRole belongsTo Department
 *    - DepartmentRole belongsTo Role (global Laratrust role)
 *    - DepartmentRole belongsToMany SchoolSection (via BelongsToSections trait)
 *
 * 4. Future considerations:
 *    - If a school never uses sections, the school_section links will simply remain empty.
 *    - Custom display names per department/section can be derived in the frontend by
 *      combining Role::display_name + Department::name + Section names.
 *    - No direct User ↔ DepartmentRole assignment – permissions and base assignment
 *      come from global Role → User (Laratrust), organizational grouping from Department → Role.
 *
 * This design strikes the right balance:
 *   • Keeps RBAC simple and standard (Laratrust)
 *   • Provides meaningful department + section grouping
 *   • Avoids redundant direct user-department pivots
 *   • Remains performant and maintainable
 */
class DepartmentRole extends Pivot
{
    use HasFactory, SoftDeletes, LogsActivity, HasUuids, BelongsToSections;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'department_role';

    /**
     * Indicates if the model should increment the primary key.
     * Pivot models with UUIDs do not auto-increment.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'department_id',
        'role_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Configure activity logging options.
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('department_role')
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => "Department role assignment was {$eventName}");
    }

    /**
     * Get the department that this role assignment belongs to.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Employee\Department::class);
    }

    /**
     * Get the global role (Laratrust) that is assigned to the department.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Role::class);
    }
}

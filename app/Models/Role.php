<?php

namespace App\Models;

use Abbasudo\Purity\Traits\Filterable;
use Abbasudo\Purity\Traits\Sortable;
use App\Models\Employee\Department;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Laratrust\Models\Role as RoleModel;

/**
 * Role — permission bundle definition (Permission Phase 2).
 *
 * Roles are only permission bundles. They have no inherent authorization meaning.
 * Capability decisions must use permissions, not role names.
 *
 * Authorization scope (exactly one):
 *   school_id = NULL  → tenant/global role definition
 *   school_id = S     → school-local role definition
 *
 * Identity (immutable after create): name within its school scope.
 * Presentation: display_name (optional), description (optional).
 * Lifecycle flag: disabled (default false) — does not delete or revoke.
 *
 * BelongsToSchool is intentionally not used: tenant roles require school_id = NULL
 * and must not be auto-assigned an active school or hidden by SchoolScope.
 */
class Role extends RoleModel
{
    use Filterable;
    use Sortable;
    use HasUuids;
    use HasTableQuery;

    /**
     * Override Laratrust parent fillable so school_id and disabled are mass-assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'disabled',
        'school_id',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'disabled' => false,
    ];

    /**
     * @var array<string>
     */
    protected array $hiddenTableColumns = [
        'id',
    ];

    /**
     * @var array<string>
     */
    protected array $defaultHiddenColumns = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'disabled' => 'boolean',
        'created_at' => 'date',
        'updated_at' => 'datetime',
    ];

    /**
     * @var array<string>
     */
    protected array $globalFilterFields = [
        'name',
        'display_name',
        'description',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::updating(function (self $role) {
            if ($role->isDirty('name')) {
                throw new \RuntimeException(
                    'Role name is immutable after creation.'
                );
            }

            if ($role->isDirty('school_id')) {
                throw new \RuntimeException(
                    'Role school scope (school_id) is immutable after creation.'
                );
            }
        });
    }

    /**
     * Explicit school association (nullable = tenant/global).
     * No auto-assign, no global SchoolScope.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function departments()
    {
        return $this->belongsToMany(Department::class, 'department_role', 'role_id', 'department_id')
            ->withTimestamps();
    }

    /**
     * Tenant / global role definition (school_id IS NULL).
     */
    public function isTenant(): bool
    {
        return $this->school_id === null;
    }

    /**
     * School-local role definition.
     */
    public function isSchoolLocal(): bool
    {
        return $this->school_id !== null;
    }

    public function isDisabled(): bool
    {
        return (bool) $this->disabled;
    }

    public function isEnabled(): bool
    {
        return ! $this->isDisabled();
    }

    /**
     * Presentation label: display_name when set, otherwise humanized name.
     * Does not alter stored identity.
     */
    public function presentationName(): string
    {
        if (filled($this->display_name)) {
            return (string) $this->display_name;
        }

        return Str::headline(str_replace(['_', '-'], ' ', (string) $this->name));
    }

    /**
     * Query scope: enabled roles only.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeEnabled($query)
    {
        return $query->where('disabled', false);
    }

    /**
     * Query scope: disabled roles only.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeDisabled($query)
    {
        return $query->where('disabled', true);
    }

    /**
     * Query scope: tenant/global definitions.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeTenant($query)
    {
        return $query->whereNull('school_id');
    }

    /**
     * Query scope: definitions for a specific school (local only, not tenant).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @param  string  $schoolId
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForSchool($query, string $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }
}

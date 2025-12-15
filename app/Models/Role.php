<?php

namespace App\Models;

use Abbasudo\Purity\Traits\Filterable;
use Abbasudo\Purity\Traits\Sortable;
use App\Models\Employee\Department;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laratrust\Models\Role as RoleModel;

class Role extends RoleModel
{
    use Filterable, Sortable, HasUuids, BelongsToSchool, HasTableQuery;
    public $guarded = [];

    public function departments()
    {
        return $this->belongsToMany(Department::class, 'department_role', 'role_id', 'department_id')
            ->withTimestamps();
    }
}
